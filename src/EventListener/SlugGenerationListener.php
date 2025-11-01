<?php

namespace App\EventListener;

use Doctrine\ORM\Events;
use App\Service\SlugService;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SlugGenerationListener
{
    public function __construct(
        private readonly SlugService $slugGenerator,
    ) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->slugGenerator->supports($entity)) {
            $this->slugGenerator->generate($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->slugGenerator->supports($entity)) {
            return;
        }

        $this->slugGenerator->generate($entity);

        /** @var EntityManagerInterface $em */
        $em = $args->getObjectManager();
        $meta = $em->getClassMetadata($entity::class);
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $entity);
    }
}
