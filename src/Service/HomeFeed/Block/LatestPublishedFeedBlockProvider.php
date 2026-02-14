<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 140)]
final class LatestPublishedFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $fetchLimit = max(60, $context->getCardsPerBlock() * 5);
        $viewer = $context->getViewer();

        if ($viewer !== null) {
            $items = $this->ppBaseRepository->findLatestPublishedExcludingCreator($viewer, $fetchLimit);
        } else {
            $items = $this->ppBaseRepository->findLatestPublished($fetchLimit);
        }

        if ($items === []) {
            return null;
        }

        return new HomeFeedBlock(
            'latest',
            'Derniers projets présentés',
            $items,
            false
        );
    }
}

