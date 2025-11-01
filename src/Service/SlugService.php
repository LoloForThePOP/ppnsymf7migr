<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Generates and assigns unique slugs to supported entities.
 *
 * This service does NOT persist or flush — it only modifies the entity.
 * Doctrine listeners or controllers decide when to persist.
 */
class SlugService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {}

    /**
     * Generates a unique slug for the given entity and assigns it
     * to the proper property (e.g. usernameSlug, stringId).
     */
    public function generate(object $entity): void
    {
        $repository = $this->em->getRepository($entity::class);

        // Identify slug field and source text
        [$fieldName, $baseString] = $this->getSlugSource($entity);

        if (empty($baseString)) {
            // No text to slugify → skip gracefully
            return;
        }

        // Normalize & lowercase
        $baseSlug = strtolower($this->slugger->slug($baseString)->toString());
        $slug = $baseSlug;
        $counter = 1;

        // If entity already has same slug, skip regeneration
        $getter = 'get' . ucfirst($fieldName);
        if (method_exists($entity, $getter) && $entity->$getter() === $slug) {
            return;
        }

        // Ensure uniqueness
        while ($this->slugExists($repository, $fieldName, $slug, $entity->getId() ?? null)) {
            $slug = sprintf('%s-%d', $baseSlug, $counter++);
        }

        // Assign final slug
        $setter = 'set' . ucfirst($fieldName);
        if (!method_exists($entity, $setter)) {
            throw new \LogicException(sprintf(
                'Entity "%s" missing "%s()" setter.',
                $entity::class,
                $setter
            ));
        }

        $entity->$setter($slug);
        // No persist/flush here → done by Doctrine lifecycle.
    }

    /**
     * Determine which field to use for slug and what source text to base it on.
     *
     * @return array [slugFieldName, sourceString]
     */
    private function getSlugSource(object $entity): array
    {
        if ($entity instanceof User) {
            return ['usernameSlug', $entity->getUsername()];
        }

        if ($entity instanceof PPBase) {
            return ['stringId', $entity->getTitle() ?: $entity->getGoal()];
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported entity type "%s" for slug generation.',
            $entity::class
        ));
    }

    /**
     * Checks whether a slug already exists (excluding current entity).
     */
    private function slugExists(object $repository, string $fieldName, string $slug, ?int $excludeId = null): bool
    {
        $qb = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where("e.$fieldName = :slug")
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        // Optional: skip soft-deleted rows
        if (property_exists($repository->getClassName(), 'deletedAt')) {
            $qb->andWhere('e.deletedAt IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
