<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\FollowRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 310)]
final class FollowedProjectsFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const FETCH_MULTIPLIER = 8;
    private const FETCH_MIN = 48;
    private const SHUFFLE_WINDOW_MULTIPLIER = 6;
    private const SHUFFLE_WINDOW_MIN = 36;

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

        $fetchLimit = max(self::FETCH_MIN, $context->getCardsPerBlock() * self::FETCH_MULTIPLIER);
        $items = $this->followRepository->findLatestFollowedPresentations($viewer, $fetchLimit);
        if ($items === []) {
            return null;
        }
        $items = HomeFeedCollectionUtils::shuffleTopWindow(
            $items,
            $context->getCardsPerBlock(),
            self::SHUFFLE_WINDOW_MULTIPLIER,
            self::SHUFFLE_WINDOW_MIN
        );

        return new HomeFeedBlock(
            'followed-projects',
            'Projets suivis',
            $items,
            true
        );
    }
}
