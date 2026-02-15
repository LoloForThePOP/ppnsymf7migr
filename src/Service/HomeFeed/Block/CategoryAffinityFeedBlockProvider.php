<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\User;
use App\Repository\PPBaseRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 320)]
final class CategoryAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const FETCH_MULTIPLIER = 10;
    private const MIN_FETCH = 72;
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

            $fetchLimit = max(
                self::MIN_FETCH,
                $context->getCardsPerBlock() * self::FETCH_MULTIPLIER
            );
            $items = $this->ppBaseRepository->findPublishedByCategoriesForCreator(
                $viewer,
                $categories,
                $fetchLimit
            );
            if ($items === [] && $usedPreferenceCategories) {
                $fallbackCategories = $this->resolveCreatorCategories($viewer);
                if ($fallbackCategories !== []) {
                    $items = $this->ppBaseRepository->findPublishedByCategoriesForCreator(
                        $viewer,
                        $fallbackCategories,
                        $fetchLimit
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

        $fetchLimit = max(self::MIN_FETCH, $context->getCardsPerBlock() * self::FETCH_MULTIPLIER);
        $items = $this->ppBaseRepository->findPublishedByCategories($anonCategoryHints, $fetchLimit);
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
