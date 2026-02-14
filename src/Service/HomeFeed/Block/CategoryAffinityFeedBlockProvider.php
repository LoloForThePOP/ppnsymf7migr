<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 320)]
final class CategoryAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
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

            if ($categories === []) {
                return null;
            }

            $items = $this->ppBaseRepository->findPublishedByCategoriesForCreator(
                $viewer,
                array_keys($categories),
                $fetchLimit
            );
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
}

