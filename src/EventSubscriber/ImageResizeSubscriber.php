<?php
// src/EventSubscriber/ImageResizeSubscriber.php

namespace App\EventSubscriber;

use App\Service\ImageResizerService;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ImageResizeSubscriber implements EventSubscriberInterface
{
    
    public function __construct(
        private readonly ImageResizerService $resizer,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::POST_UPLOAD => 'onPostUpload',
        ];
    }

    public function onPostUpload(Event $event): void
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        // Get the Vich field name (used by your service)
        $fieldName = $mapping->getFilePropertyName();

        // Call your resizer service
        $this->resizer->edit($object, $fieldName);
    }


}
