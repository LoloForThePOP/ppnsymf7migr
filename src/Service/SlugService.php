<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\PPBase;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class SlugService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {}

    /**
     * Check if this entity type supports slug generation.
     */
    public function supports(object $entity): bool
    {
        return $entity instanceof User || $entity instanceof PPBase || $entity instanceof Article;
    }

    /**
     * Generates a unique slug for the given entity and assigns it
     * to the correct property (e.g., usernameSlug, stringId).
     */
    public function generate(object $entity): void
    {
        [$fieldName, $baseString] = $this->getSlugSource($entity);

        if (empty($baseString)) {
            return;
        }

        $repository = $this->em->getRepository($entity::class);
        $baseSlug = strtolower($this->slugger->slug($baseString)->toString());
        $slug = $baseSlug;
        $counter = 1;

        $getter = 'get' . ucfirst($fieldName);
        if (method_exists($entity, $getter) && $entity->$getter() === $slug) {
            return;
        }

        while ($this->slugExists($repository, $fieldName, $slug, $entity->getId() ?? null)) {
            $slug = sprintf('%s-%d', $baseSlug, $counter++);
        }

        $setter = 'set' . ucfirst($fieldName);
        if (!method_exists($entity, $setter)) {
            throw new \LogicException(sprintf(
                'Entity "%s" missing "%s()" setter.',
                $entity::class,
                $setter
            ));
        }

        $entity->$setter($slug);
    }

    private function getSlugSource(object $entity): array
    {
        if ($entity instanceof User) {
            return ['usernameSlug', $entity->getUsername()];
        }

        if ($entity instanceof PPBase) {
            return ['stringId', $entity->getTitle() ?: $entity->getGoal()];
        }

        if ($entity instanceof Article) {
            return ['slug', $entity->getTitle()];
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported entity type "%s" for slug generation.',
            $entity::class
        ));
    }

    private function slugExists(object $repository, string $fieldName, string $slug, ?int $excludeId = null): bool
    {
        $qb = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where("e.$fieldName = :slug")
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        if (property_exists($repository->getClassName(), 'deletedAt')) {
            $qb->andWhere('e.deletedAt IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
