<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\FollowRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 280)]
final class FollowedProjectsFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    public function __construct(
        private readonly FollowRepository $followRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        if (!$context->isLoggedIn()) {
            return null;
        }

        $viewer = $context->getViewer();
        if ($viewer === null) {
            return null;
        }

        $fetchLimit = max(24, $context->getCardsPerBlock() * 4);
        $items = $this->followRepository->findLatestFollowedPresentations($viewer, $fetchLimit);
        if ($items === []) {
            return null;
        }

        return new HomeFeedBlock(
            'followed-projects',
            'Projets suivis',
            $items,
            true
        );
    }
}

