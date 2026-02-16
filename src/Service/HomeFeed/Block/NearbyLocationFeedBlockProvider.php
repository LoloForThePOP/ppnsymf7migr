<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 260)]
final class NearbyLocationFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const FETCH_MULTIPLIER = 10;
    private const FETCH_MIN = 72;
    private const SHUFFLE_WINDOW_MULTIPLIER = 8;
    private const SHUFFLE_WINDOW_MIN = 48;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $location = $context->getLocationHint();
        if ($location === null) {
            return null;
        }

        $fetchLimit = max(self::FETCH_MIN, $context->getCardsPerBlock() * self::FETCH_MULTIPLIER);
        $items = $this->ppBaseRepository->findPublishedNearLocation(
            $location['lat'],
            $location['lng'],
            $location['radius'],
            $fetchLimit,
            $context->getViewer()
        );

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
            'around-you',
            'Autour de vous',
            $items,
            true
        );
    }
}
