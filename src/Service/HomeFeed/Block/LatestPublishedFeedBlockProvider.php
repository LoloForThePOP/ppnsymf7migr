<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 320)]
final class LatestPublishedFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const FETCH_MULTIPLIER = 8;
    private const FETCH_MIN = 80;
    private const SHUFFLE_WINDOW_MULTIPLIER = 8;
    private const SHUFFLE_WINDOW_MIN = 64;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $fetchLimit = max(self::FETCH_MIN, $context->getCardsPerBlock() * self::FETCH_MULTIPLIER);
        $viewer = $context->getViewer();

        if ($viewer !== null) {
            $items = $this->ppBaseRepository->findLatestPublishedExcludingCreator($viewer, $fetchLimit);
        } else {
            $items = $this->ppBaseRepository->findLatestPublished($fetchLimit);
        }

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
            'latest',
            'Derniers projets présentés',
            $items,
            false
        );
    }
}
