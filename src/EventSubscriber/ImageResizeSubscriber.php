<?php

namespace App\EventSubscriber;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

/**
 * Downscales oversized uploaded images before they are persisted, to save storage and bandwidth.
 */
final class ImageResizeSubscriber implements EventSubscriberInterface
{
    private const LOGO_RESIZE_MIN_BYTES = 350 * 1024;

    /**
     * Per-mapping resizing rules.
     *
     * @var array<string, array{max_width:int, max_height:int, quality:int}>
     */
    private array $rules = [
        'presentation_slide_file' => ['max_width' => 1920, 'max_height' => 1080, 'quality' => 80],
        'project_logo_image' => ['max_width' => 1400, 'max_height' => 1400, 'quality' => 82],
        'project_custom_thumbnail_image' => ['max_width' => 1400, 'max_height' => 1400, 'quality' => 82],
        'profile_image' => ['max_width' => 900, 'max_height' => 900, 'quality' => 82],
        'news_image' => ['max_width' => 1920, 'max_height' => 1080, 'quality' => 80],
    ];

    private ImagineInterface $imagine;

    public function __construct(?ImagineInterface $imagine = null, private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->imagine = $imagine ?? new \Imagine\Imagick\Imagine();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_UPLOAD => 'onPreUpload',
        ];
    }

    public function onPreUpload(Event $event): void
    {
        $mapping = $event->getMapping();
        $mappingName = $mapping->getMappingName();

        if (!isset($this->rules[$mappingName])) {
            return;
        }

        $file = $mapping->getFile($event->getObject());
        if (!$file || !is_file($file->getPathname())) {
            return;
        }

        $fileSize = $file->getSize();
        if ($mappingName === 'project_logo_image'
            && is_int($fileSize)
            && $fileSize > 0
            && $fileSize <= self::LOGO_RESIZE_MIN_BYTES) {
            return;
        }

        $rule = $this->rules[$mappingName];
        $originalContent = null;
        if ($mappingName === 'project_logo_image') {
            $originalContent = @file_get_contents($file->getPathname());
        }

        try {
            $image = $this->imagine->open($file->getPathname());
            $size = $image->getSize();

            if ($size->getWidth() <= $rule['max_width'] && $size->getHeight() <= $rule['max_height']) {
                return; // already within bounds
            }

            $box = new Box($rule['max_width'], $rule['max_height']);
            $image
                ->thumbnail($box, ImageInterface::THUMBNAIL_INSET)
                ->save($file->getPathname(), ['quality' => $rule['quality']]);
        } catch (\Throwable $e) {
            if (is_string($originalContent)) {
                @file_put_contents($file->getPathname(), $originalContent);
            }
            $this->logger->warning('Image resize failed', [
                'mapping' => $mappingName,
                'path' => $file->getPathname(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
