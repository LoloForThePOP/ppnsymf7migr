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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        if (str_starts_with($sourcePath, 'http://') || str_starts_with($sourcePath, 'https://')) {
            // Remote source (e.g. YouTube thumb) → use directly
            $newUrl = $sourcePath;
        } else {
            $newUrl = $this->cacheManager->generateUrl(
                $sourcePath,
                self::FILTER,
                [],
                null,
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

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
            $sorted = $slides->toArray();
            usort($sorted, function (Slide $a, Slide $b) {
                return (int) $a->getPosition() <=> (int) $b->getPosition();
            });
            /** @var Slide $slide */
            $slide = $sorted[0];

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
            $sourcePath = $this->extractSourcePathFromCacheUrl($cachedUrl);
            if ($sourcePath === null) {
                return;
            }

            $this->cacheManager->remove($sourcePath, self::FILTER);

            // 2️⃣ If local resolver (web_path), also remove physical file if it exists
            $path = $this->getLocalCachePathForSource($sourcePath);
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

        $sourcePath = $this->extractSourcePathFromCacheUrl($cachedUrl);
        if ($sourcePath === null) {
            return;
        }

        $path = $this->getLocalCachePathForSource($sourcePath);
        if ($path && !$this->filesystem->exists($path)) {
            // File missing → safe to clear the Liip cache entry too
            $this->cacheManager->remove($sourcePath, self::FILTER);
        }
    }

    /**
     * Resolves a browser URL to its original source path if it is a Liip cache URL.
     */
    private function extractSourcePathFromCacheUrl(string $cachedUrl): ?string
    {
        $path = parse_url($cachedUrl, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $resolvePrefix = '/media/cache/resolve/' . self::FILTER . '/';
        $cachePrefix = '/media/cache/' . self::FILTER . '/';

        $pos = strpos($path, $resolvePrefix);
        if ($pos !== false) {
            return substr($path, $pos + strlen($resolvePrefix));
        }

        $pos = strpos($path, $cachePrefix);
        if ($pos !== false) {
            return substr($path, $pos + strlen($cachePrefix));
        }

        return null;
    }

    /**
     * Resolves a source path to its local filesystem cached path
     * (works only with the default web_path resolver).
     */
    private function getLocalCachePathForSource(string $sourcePath): ?string
    {
        $sanitized = str_replace('://', '---', ltrim($sourcePath, '/'));
        $localPath = sprintf(
            '%s/public/media/cache/%s/%s',
            $this->projectDir,
            self::FILTER,
            $sanitized
        );

        return $localPath;
    }
}
