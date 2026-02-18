<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 280)]
final class AnonExploreFallbackFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const FETCH_MULTIPLIER = 7;
    private const FETCH_MIN = 56;
    private const SHUFFLE_WINDOW_MULTIPLIER = 7;
    private const SHUFFLE_WINDOW_MIN = 42;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        if ($context->isLoggedIn()) {
            return null;
        }

        if ($this->hasAnonymousSignals($context)) {
            return null;
        }

        $fetchLimit = max(self::FETCH_MIN, $context->getCardsPerBlock() * self::FETCH_MULTIPLIER);
        $items = $this->ppBaseRepository->findLatestPublishedWindow($fetchLimit);

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
            'anon-explore-fallback',
            'Explorer sur Propon',
            $items,
            false
        );
    }

    private function hasAnonymousSignals(HomeFeedContext $context): bool
    {
        return $context->getAnonCategoryHints() !== []
            || $context->getAnonKeywordHints() !== []
            || $context->getAnonRecentViewIds() !== []
            || $context->getLocationHint() !== null;
    }
}

