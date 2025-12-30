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
        if ($baseSlug === '') {
            return;
        }

        $maxLength = $this->getSlugMaxLength($entity, $fieldName);
        if ($maxLength !== null) {
            $baseSlug = $this->truncateSlug($baseSlug, $maxLength);
        }

        if ($baseSlug === '') {
            return;
        }
        $slug = $baseSlug;
        $counter = 1;

        $getter = 'get' . ucfirst($fieldName);
        if (method_exists($entity, $getter) && $entity->$getter() === $slug) {
            return;
        }

        while ($this->slugExists($repository, $fieldName, $slug, $entity->getId() ?? null)) {
            $suffix = '-' . $counter++;
            if ($maxLength !== null) {
                $availableLength = $maxLength - strlen($suffix);
                $availableLength = max(0, $availableLength);
                $slug = $this->truncateSlug($baseSlug, $availableLength) . $suffix;
            } else {
                $slug = $baseSlug . $suffix;
            }
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

        if ($entity instanceof PPBase) {
            // Once a human-friendly slug is generated, mark it as non-random to prevent silent changes later.
            $entity->getExtra()->setIsRandomizedStringId(false);
        }
    }

    private function getSlugMaxLength(object $entity, string $fieldName): ?int
    {
        if ($entity instanceof PPBase && $fieldName === 'stringId') {
            return 190;
        }

        if ($entity instanceof User && $fieldName === 'usernameSlug') {
            return 120;
        }

        if ($entity instanceof Article && $fieldName === 'slug') {
            return 255;
        }

        return null;
    }

    private function truncateSlug(string $slug, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (strlen($slug) <= $maxLength) {
            return $slug;
        }

        return rtrim(substr($slug, 0, $maxLength), '-');
    }

    private function getSlugSource(object $entity): array
    {
        if ($entity instanceof User) {
            return ['usernameSlug', $entity->getUsername()];
        }

        if ($entity instanceof PPBase) {
            // Do not regenerate slugs for published projects when the slug has been finalized.
            if ($entity->getStringId() && !$entity->getExtra()->isRandomizedStringId() && $entity->isPublished()) {
                return ['', null];
            }

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
