<?php

namespace App\Service;

use App\Entity\PPBase;
use App\Entity\Slide;
use App\Enum\SlideType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handles creation, refresh and cleanup of cached thumbnails for project presentations.
 *
 * Logic:
 *  1. Prefer a user-uploaded custom thumbnail.
 *  2. Otherwise use the first slide (image or Youtube thumbnail if slide is a Youtube video).
 *  3. Otherwise use the project logo.
 *  4. If none are found, clears the cached thumbnail reference (frontend will use a default thumbnail (like one letter thumbnail)).
 *
 *  Works with the default LiipImagine web_path resolver but
 *  remains compatible with remote resolvers (S3, Flysystem).
 */
class CacheThumbnailService
{
    private const FILTER = 'standard_thumbnail_md_test';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager $cacheManager,
        private readonly Filesystem $filesystem,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {}

    /**
     * Updates or regenerates the cached thumbnail for a project.
     * Cleans up obsolete cache files if needed.
     */
    public function updateThumbnail(PPBase $project, bool $forceRefresh = false): void
    {
        $extra = $project->getExtra();
        $currentUrl = $extra->getCacheThumbnailUrl();

        $sourcePath = $this->determineSourceImage($project);

        // Case 1 — no image available → clear old cache if any
        if ($sourcePath === null) {
            $this->removeCachedThumbnail($currentUrl);
            $extra->setCacheThumbnailUrl(null);
            $this->em->flush();
            return;
        }

        // Case 2 — generate (or fetch) a new cached URL
        $newUrl = $this->cacheManager->resolve($sourcePath, self::FILTER);

        // If changed or forced refresh → remove old cache and update
        if ($forceRefresh || $newUrl !== $currentUrl) {
            $this->removeCachedThumbnail($currentUrl);
            $extra->setCacheThumbnailUrl($newUrl);
        }

        // Optional: ensure file exists when using local web_path resolver
        $this->cleanupOrphanCache($newUrl);

        $this->em->flush();
    }

    /**
     * Determine which image should serve as the thumbnail source.
     */
    private function determineSourceImage(PPBase $project): ?string
    {
        if ($project->getCustomThumbnail()) {
            return $this->uploaderHelper->asset($project, 'customThumbnailFile');
        }

        $slides = $project->getSlides();
        if (!$slides->isEmpty()) {
            /** @var Slide $slide */
            $slide = $slides->first();

            if ($slide->getType() === SlideType::IMAGE) {
                return $this->uploaderHelper->asset($slide, 'imageFile');
            }

            $videoId = $slide->getYoutubeVideoId();
            if ($slide->getType() === SlideType::YOUTUBE_VIDEO && $videoId) {
                return sprintf('https://img.youtube.com/vi/%s/mqdefault.jpg', $videoId);
            }
        }

        if ($project->getLogo()) {
            return $this->uploaderHelper->asset($project, 'logoFile');
        }

        return null;
    }

    /**
     * Removes a cached thumbnail (both resolver and local file if applicable).
     */
    private function removeCachedThumbnail(?string $cachedUrl): void
    {
        if (!$cachedUrl) {
            return;
        }

        try {
            // 1️⃣ Ask LiipImagine to remove its cache (works for all resolvers)
            $this->cacheManager->remove($cachedUrl, self::FILTER);

            // 2️⃣ If local resolver (web_path), also remove physical file if it exists
            $path = $this->getLocalCachePath($cachedUrl);
            if ($path && $this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        } catch (\Throwable $e) {
            // Non-fatal — log in prod if needed, but don't break the flow
        }
    }

    /**
     * Ensures a cached file exists (only relevant for local web_path resolver).
     * Clears the cache reference if the file was deleted.
     */
    private function cleanupOrphanCache(?string $cachedUrl): void
    {
        if (!$cachedUrl) {
            return;
        }

        $path = $this->getLocalCachePath($cachedUrl);
        if ($path && !$this->filesystem->exists($path)) {
            // File missing → safe to clear the Liip cache entry too
            $this->cacheManager->remove($cachedUrl, self::FILTER);
        }
    }

    /**
     * Resolves a browser URL to its local filesystem path
     * (works only with the default web_path resolver).
     */
    private function getLocalCachePath(string $browserUrl): ?string
    {
        $path = parse_url($browserUrl, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $localPath = $this->projectDir . '/public' . $path;
        return $this->filesystem->exists($localPath) ? $localPath : null;
    }
}
