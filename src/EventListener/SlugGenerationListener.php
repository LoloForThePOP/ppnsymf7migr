<?php

namespace App\EventListener;

use App\Entity\PPBase;
use App\Entity\Profile;
use Doctrine\ORM\Events;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

/**
 * Automatically generates unique slugs before saving entities.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class SlugListener
{
    public function __construct(
        private readonly SlugService $slugGenerator,
    ) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Profile || $entity instanceof PPBase) {
            $this->slugGenerator->generate($entity);
        }
    }

     public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof Profile || $entity instanceof PPBase)) {
            return;
        }



        // Force Doctrine to see the change
        $this->slugGenerator->generate($entity);

        /** @var EntityManagerInterface $om */
        $om = $args->getObjectManager();

        $meta = $om->getClassMetadata($entity::class);
        $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $entity);



    }



}



