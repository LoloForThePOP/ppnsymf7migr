<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 260)]
final class NearbyLocationFeedBlockProvider implements HomeFeedBlockProviderInterface
{
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

        $fetchLimit = max(36, $context->getCardsPerBlock() * 6);
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

        return new HomeFeedBlock(
            'around-you',
            'Autour de vous',
            $items,
            true
        );
    }
}
