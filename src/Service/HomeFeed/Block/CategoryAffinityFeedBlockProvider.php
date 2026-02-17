<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 350)]
final class CategoryAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const ANON_CACHE_KEY_PREFIX = 'home_feed_anon_category_affinity_v1_';
    private const LOGGED_CACHE_KEY_PREFIX = 'home_feed_logged_category_affinity_v1_';
    private const WINDOW_FETCH_MULTIPLIER_LOGGED = 6;
    private const WINDOW_FETCH_MIN_LOGGED = 48;
    private const WINDOW_FETCH_MULTIPLIER_ANON = 1;
    private const WINDOW_FETCH_MIN_ANON = 16;
    private const WINDOW_OFFSETS_LOGGED_PREFERENCE = [0];
    private const WINDOW_OFFSETS_LOGGED_FALLBACK = [0, 180];
    private const WINDOW_OFFSETS_ANON = [0];
    private const MERGED_CANDIDATE_LIMIT = 900;
    private const PRIMARY_POOL_MULTIPLIER = 6;
    private const PRIMARY_POOL_MIN = 36;
    private const SECONDARY_POOL_MULTIPLIER = 2;
    private const SECONDARY_POOL_MIN = 12;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire('%app.home_feed.category_affinity.logged_cache_ttl_seconds%')]
        private readonly int $loggedCategoryCacheTtlSeconds,
        #[Autowire('%app.home_feed.category_affinity.anon_cache_ttl_seconds%')]
        private readonly int $anonCategoryCacheTtlSeconds,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        if ($context->isLoggedIn()) {
            $viewer = $context->getViewer();
            if ($viewer === null) {
                return null;
            }

            $categories = $this->userPreferenceRepository->findTopCategorySlugsForUser($viewer, 8);
            $usedPreferenceCategories = $categories !== [];

            if ($categories === []) {
                $categories = $this->resolveCreatorCategories($viewer);
            }

            if ($categories === []) {
                return null;
            }

            $fetchLimit = max(
                self::WINDOW_FETCH_MIN_LOGGED,
                $context->getCardsPerBlock() * self::WINDOW_FETCH_MULTIPLIER_LOGGED
            );
            if ($usedPreferenceCategories) {
                $items = $this->collectLoggedPreferenceCategoryCandidates($viewer, $categories, $fetchLimit);
            } else {
                $items = $this->collectCategoryCandidates(
                    $categories,
                    $fetchLimit,
                    $viewer,
                    self::WINDOW_OFFSETS_LOGGED_FALLBACK
                );
            }

            if ($items === [] && $usedPreferenceCategories) {
                $fallbackCategories = $this->resolveCreatorCategories($viewer);
                if ($fallbackCategories !== []) {
                    $items = $this->collectCategoryCandidates(
                        $fallbackCategories,
                        $fetchLimit,
                        $viewer,
                        self::WINDOW_OFFSETS_LOGGED_FALLBACK
                    );
                }
            }

            if ($items === []) {
                return null;
            }
            $items = $this->diversifyItems($items, $context->getCardsPerBlock());

            return new HomeFeedBlock(
                'category-affinity',
                'Basé sur vos catégories',
                $items,
                true
            );
        }

        $anonCategoryHints = $context->getAnonCategoryHints();
        if ($anonCategoryHints === []) {
            return null;
        }

        $fetchLimit = max(
            self::WINDOW_FETCH_MIN_ANON,
            $context->getCardsPerBlock() * self::WINDOW_FETCH_MULTIPLIER_ANON
        );
        $items = $this->collectAnonCategoryCandidates($anonCategoryHints, $fetchLimit);
        if ($items === []) {
            return null;
        }
        $items = $this->diversifyItems($items, $context->getCardsPerBlock());

        return new HomeFeedBlock(
            'anon-category-affinity',
            'Selon vos centres d’intérêt récents',
            $items,
            true
        );
    }

    /**
     * @param string[] $categories
     * @param int[] $windowOffsets
     *
     * @return array<int,mixed>
     */
    private function collectCategoryCandidates(
        array $categories,
        int $windowLimit,
        ?User $excludeCreator,
        array $windowOffsets
    ): array {
        $batches = [];

        foreach ($windowOffsets as $offset) {
            $batch = $this->ppBaseRepository->findPublishedByCategoriesWindow(
                $categories,
                $windowLimit,
                $offset,
                $excludeCreator
            );
            if ($batch === []) {
                break;
            }

            $batches[] = $batch;
            if (count($batch) < $windowLimit) {
                break;
            }
        }

        if ($batches === []) {
            return [];
        }

        $merged = HomeFeedCollectionUtils::mergeUniquePresentations(...$batches);

        return count($merged) > self::MERGED_CANDIDATE_LIMIT
            ? array_slice($merged, 0, self::MERGED_CANDIDATE_LIMIT)
            : $merged;
    }

    /**
     * @param string[] $categories
     *
     * @return array<int,mixed>
     */
    private function collectLoggedPreferenceCategoryCandidates(User $viewer, array $categories, int $windowLimit): array
    {
        $cacheItem = $this->cache->getItem($this->buildLoggedCacheKey($viewer, $categories, $windowLimit));

        if ($cacheItem->isHit()) {
            $cachedIds = $this->normalizeCachedIds($cacheItem->get());
            if ($cachedIds !== []) {
                return $this->ppBaseRepository->findPublishedByIdsPreserveOrder($cachedIds);
            }
        }

        $items = $this->collectCategoryCandidates(
            $categories,
            $windowLimit,
            $viewer,
            self::WINDOW_OFFSETS_LOGGED_PREFERENCE
        );

        $cachedIds = [];
        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $itemId = $item->getId();
            if ($itemId === null || $itemId <= 0) {
                continue;
            }

            $cachedIds[] = $itemId;
        }

        $cacheItem->set($cachedIds);
        $cacheItem->expiresAfter(max(20, $this->loggedCategoryCacheTtlSeconds));
        $this->cache->save($cacheItem);

        return $items;
    }

    /**
     * @param string[] $categories
     *
     * @return array<int,mixed>
     */
    private function collectAnonCategoryCandidates(array $categories, int $windowLimit): array
    {
        $cacheItem = $this->cache->getItem($this->buildAnonCacheKey($categories, $windowLimit));

        if ($cacheItem->isHit()) {
            $cachedIds = $this->normalizeCachedIds($cacheItem->get());
            if ($cachedIds !== []) {
                return $this->ppBaseRepository->findPublishedByIdsPreserveOrder($cachedIds);
            }
        }

        $items = $this->collectCategoryCandidates($categories, $windowLimit, null, self::WINDOW_OFFSETS_ANON);

        $cachedIds = [];
        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $itemId = $item->getId();
            if ($itemId === null || $itemId <= 0) {
                continue;
            }

            $cachedIds[] = $itemId;
        }

        $cacheItem->set($cachedIds);
        $cacheItem->expiresAfter(max(30, $this->anonCategoryCacheTtlSeconds));
        $this->cache->save($cacheItem);

        return $items;
    }

    /**
     * @return int[]
     */
    private function normalizeCachedIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id <= 0 || isset($ids[$id])) {
                continue;
            }

            $ids[$id] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param string[] $categories
     */
    private function buildAnonCacheKey(array $categories, int $windowLimit): string
    {
        $normalized = [];
        foreach ($categories as $category) {
            $slug = trim((string) $category);
            if ($slug === '') {
                continue;
            }

            $normalized[$slug] = true;
        }

        $slugs = array_keys($normalized);
        sort($slugs, SORT_STRING);

        $payload = implode('|', $slugs) . ':' . max(1, $windowLimit);

        return self::ANON_CACHE_KEY_PREFIX . hash('sha256', $payload);
    }

    /**
     * @param string[] $categories
     */
    private function buildLoggedCacheKey(User $viewer, array $categories, int $windowLimit): string
    {
        $normalized = [];
        foreach ($categories as $category) {
            $slug = trim((string) $category);
            if ($slug === '') {
                continue;
            }

            $normalized[$slug] = true;
        }

        $slugs = array_keys($normalized);
        sort($slugs, SORT_STRING);

        $payload = implode('|', $slugs)
            . ':' . max(1, $windowLimit)
            . ':u' . (string) ($viewer->getId() ?? 0);

        return self::LOGGED_CACHE_KEY_PREFIX . hash('sha256', $payload);
    }

    /**
     * Keeps the rail relevant (recent pool first) while avoiding an always-identical top slice.
     *
     * @param array<int,mixed> $items
     *
     * @return array<int,mixed>
     */
    private function diversifyItems(array $items, int $cardsPerBlock): array
    {
        $count = count($items);
        if ($count <= $cardsPerBlock) {
            return $items;
        }

        $primaryPoolSize = min(
            $count,
            max(
                self::PRIMARY_POOL_MIN,
                $cardsPerBlock * self::PRIMARY_POOL_MULTIPLIER
            )
        );
        $primaryPool = array_slice($items, 0, $primaryPoolSize);
        shuffle($primaryPool);

        $remaining = array_slice($items, $primaryPoolSize);
        if ($remaining === []) {
            return $primaryPool;
        }

        $secondaryPoolSize = min(
            count($remaining),
            max(
                self::SECONDARY_POOL_MIN,
                $cardsPerBlock * self::SECONDARY_POOL_MULTIPLIER
            )
        );
        $secondaryPool = array_slice($remaining, 0, $secondaryPoolSize);
        shuffle($secondaryPool);

        $tail = array_slice($remaining, $secondaryPoolSize);

        return array_merge($primaryPool, $secondaryPool, $tail);
    }

    /**
     * @return string[]
     */
    private function resolveCreatorCategories(User $viewer): array
    {
        $creatorRecent = $this->ppBaseRepository->findLatestByCreator($viewer, 12);
        $categories = [];

        foreach ($creatorRecent as $project) {
            foreach ($project->getCategories() as $category) {
                $slug = trim((string) $category->getUniqueName());
                if ($slug !== '') {
                    $categories[$slug] = true;
                }
            }
        }

        return array_keys($categories);
    }
}
