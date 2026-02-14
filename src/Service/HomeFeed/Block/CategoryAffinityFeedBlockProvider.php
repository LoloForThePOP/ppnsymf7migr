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
    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $fetchLimit = max(24, $context->getCardsPerBlock() * 4);

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

        $items = $this->ppBaseRepository->findPublishedByCategories($anonCategoryHints, $fetchLimit);
        if ($items === []) {
            return null;
        }

        return new HomeFeedBlock(
            'anon-category-affinity',
            'Selon vos centres d’intérêt récents',
            $items,
            true
        );
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
