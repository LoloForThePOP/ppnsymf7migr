<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Generates and assigns unique slugs to entities (Profile, PPBase, ...).
 */
class SlugService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {}

    /**
     * Generates and saves a unique slug for the given entity.
     *
     * @param object $entity The entity to generate the slug for.
     */
    public function generateSlug(object $entity): void
    {
        $slug = null;
        $fieldName = null;
        $repository = $this->em->getRepository($entity::class);

        // Determine which field and base text to slugify
        if ($entity instanceof User) {
            /** @var User $entity */
            $baseString = $entity->getUsername();
            $fieldName = 'usernameSlug';
        } elseif ($entity instanceof PPBase) {
            $baseString = $entity->getTitle() ?: $entity->getGoal();
            $fieldName = 'stringId';
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported entity type "%s" for slug generation.',
                $entity::class
            ));
        }

        // Normalize and lowercase the base slug
        $baseSlug = strtolower($this->slugger->slug($baseString)->toString());
        $slug = $baseSlug;
        $counter = 1;

        // Check for existing slugs and increment if needed
        while ($this->slugExists($repository, $fieldName, $slug)) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // Assign the final slug to the correct property
        $setter = 'set' . ucfirst($fieldName);
        if (!method_exists($entity, $setter)) {
            throw new \LogicException(sprintf(
                'The entity "%s" does not have a "%s" setter method.',
                $entity::class,
                $setter
            ));
        }
        $entity->$setter($slug);

        // Persist and save changes
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * Checks if a slug already exists in the database for the given repository and field.
     *
     * @param object $repository The Doctrine repository.
     * @param string $fieldName  The slug field name.
     * @param string $slug       The slug value to check.
     */
    private function slugExists(object $repository, string $fieldName, string $slug): bool
    {
        $qb = $repository->createQueryBuilder('e');
        $qb->select('COUNT(e.id)')
           ->where($qb->expr()->eq("e.$fieldName", ':slug'))
           ->setParameter('slug', $slug);

        // Optional: exclude soft-deleted rows (if you use "deletedAt")
        if (property_exists($repository->getClassName(), 'deletedAt')) {
            $qb->andWhere('e.deletedAt IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

}
