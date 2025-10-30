<?php

namespace App\Service;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Entity\Profile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * Resizes and optimizes uploaded images to reduce storage usage.
 *
 * Uses Intervention Image v3:
 * - Prefers Imagick (better quality and performance)
 * - Falls back to GD if Imagick is not available
 */
class ImageResizerService
{
    private ImageManager $manager;

    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
    ) {
        // Choose driver dynamically: Imagick first, GD as fallback
        $driver = extension_loaded('imagick')
            ? new ImagickDriver()
            : new GdDriver();

        // Create the image manager with the selected driver
        $this->manager = ImageManager::withDriver($driver);
    }

    /**
     * Resize and compress an image depending on its entity type.
     *
     * @param object      $imageEntity Entity containing the image field (e.g. Slide, PPBase, Persorg)
     * @param string|null $fieldName   The VichUploader field name (if the entity has multiple images)
     *
     * @return bool True if the image was successfully processed, false otherwise
     */
    public function edit(object $imageEntity, ?string $fieldName = null): bool
    {
        // Get the public asset path from VichUploaderBundle (e.g. /uploads/images/foo.jpg)
        $assetPath = $this->uploaderHelper->asset($imageEntity, $fieldName);
        if (!$assetPath) {
            return false;
        }

        // Convert to a relative local path (remove leading slash)
        $filePath = ltrim($assetPath, '/');

        // Validate that the file exists and is readable
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Skip unsupported formats such as SVG
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            return false;
        }

        // Read the image (Intervention v3 uses ->read())
        $image = $this->manager->read($filePath);

        // Define target size depending on entity type
        [$maxWidth, $maxHeight] = match (true) {
            $imageEntity instanceof Slide => [900, 900],
            $imageEntity instanceof PPBase,
            $imageEntity instanceof Profile => [340, 340],
            default => [1024, 1024],
        };

        // Resize while keeping aspect ratio and preventing upscaling
        $image->scaleDown(width: $maxWidth, height: $maxHeight);

        // Adjust compression / quality level based on file type
        $quality = match ($extension) {
            'jpg', 'jpeg' => 85,   // 0–100
            'png'         => 8,    // 0–9 (compression level)
            'webp'        => 80,   // 0–100
            'avif'        => 80,   // 0–100 
            default        => 90,
        };

        // Save the optimized image (overwrites original)
        $image->save($filePath, quality: $quality);

        $image->save(
            preg_replace('/\.[^.]+$/', '.avif', $filePath),
            quality: $quality
        );

        return true;
    }
}
