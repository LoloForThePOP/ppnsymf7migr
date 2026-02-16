<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\User;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 320)]
final class CategoryAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const WINDOW_FETCH_MULTIPLIER = 8;
    private const WINDOW_FETCH_MIN = 64;
    private const WINDOW_OFFSETS = [0, 180, 720];
    private const MERGED_CANDIDATE_LIMIT = 900;
    private const PRIMARY_POOL_MULTIPLIER = 6;
    private const PRIMARY_POOL_MIN = 36;
    private const SECONDARY_POOL_MULTIPLIER = 2;
    private const SECONDARY_POOL_MIN = 12;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
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

            $fetchLimit = max(self::WINDOW_FETCH_MIN, $context->getCardsPerBlock() * self::WINDOW_FETCH_MULTIPLIER);
            $items = $this->collectCategoryCandidates($categories, $fetchLimit, $viewer);
            if ($items === [] && $usedPreferenceCategories) {
                $fallbackCategories = $this->resolveCreatorCategories($viewer);
                if ($fallbackCategories !== []) {
                    $items = $this->collectCategoryCandidates($fallbackCategories, $fetchLimit, $viewer);
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

        $fetchLimit = max(self::WINDOW_FETCH_MIN, $context->getCardsPerBlock() * self::WINDOW_FETCH_MULTIPLIER);
        $items = $this->collectCategoryCandidates($anonCategoryHints, $fetchLimit, null);
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
     *
     * @return array<int,mixed>
     */
    private function collectCategoryCandidates(array $categories, int $windowLimit, ?User $excludeCreator): array
    {
        $batches = [];

        foreach (self::WINDOW_OFFSETS as $offset) {
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
