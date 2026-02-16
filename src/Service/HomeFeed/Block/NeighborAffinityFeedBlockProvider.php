<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\Bookmark;
use App\Repository\BookmarkRepository;
use App\Repository\FollowRepository;
use App\Repository\PresentationEventRepository;
use App\Repository\PresentationNeighborRepository;
use App\Service\AI\PresentationEmbeddingService;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 310)]
final class NeighborAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const LOGGED_SEED_LIMIT = 8;
    private const ANON_SEED_LIMIT = 8;
    private const PER_SEED_NEIGHBOR_LIMIT_MULTIPLIER = 2;
    private const PER_SEED_NEIGHBOR_LIMIT_MIN = 12;
    private const MIN_UNIQUE_RESULTS = 6;
    private const SHUFFLE_WINDOW_MULTIPLIER = 6;
    private const SHUFFLE_WINDOW_MIN = 36;

    public function __construct(
        private readonly PresentationNeighborRepository $presentationNeighborRepository,
        private readonly PresentationEventRepository $presentationEventRepository,
        private readonly FollowRepository $followRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly PresentationEmbeddingService $presentationEmbeddingService,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $seedIds = $context->isLoggedIn()
            ? $this->resolveLoggedSeedIds($context)
            : array_slice($context->getAnonRecentViewIds(), 0, self::ANON_SEED_LIMIT);

        if ($seedIds === []) {
            return null;
        }

        $cardsPerBlock = $context->getCardsPerBlock();
        $perSeedLimit = max(
            self::PER_SEED_NEIGHBOR_LIMIT_MIN,
            $cardsPerBlock * self::PER_SEED_NEIGHBOR_LIMIT_MULTIPLIER
        );

        $model = $this->presentationEmbeddingService->getModel();
        $seedCount = count($seedIds);
        $scoresByPresentation = [];
        $presentationsById = [];
        $seedSet = array_fill_keys($seedIds, true);
        $viewerId = $context->getViewer()?->getId();

        foreach ($seedIds as $seedIndex => $seedId) {
            $neighbors = $this->presentationNeighborRepository->findNeighborPresentationsById(
                $seedId,
                $model,
                $perSeedLimit
            );
            if ($neighbors === []) {
                continue;
            }

            $seedWeight = (float) max(1, $seedCount - $seedIndex);
            foreach ($neighbors as $neighborIndex => $neighbor) {
                $neighborId = $neighbor->getId();
                if ($neighborId === null || isset($seedSet[$neighborId])) {
                    continue;
                }

                if ($viewerId !== null && $neighbor->getCreator()?->getId() === $viewerId) {
                    continue;
                }

                $rankWeight = 1.0 / (1.0 + $neighborIndex);
                $score = $seedWeight * $rankWeight;

                $scoresByPresentation[$neighborId] = ($scoresByPresentation[$neighborId] ?? 0.0) + $score;
                $presentationsById[$neighborId] = $neighbor;
            }
        }

        if (count($scoresByPresentation) < self::MIN_UNIQUE_RESULTS) {
            return null;
        }

        arsort($scoresByPresentation, SORT_NUMERIC);

        $rankedItems = [];
        foreach (array_keys($scoresByPresentation) as $presentationId) {
            if (!isset($presentationsById[$presentationId])) {
                continue;
            }
            $rankedItems[] = $presentationsById[$presentationId];
        }

        if ($rankedItems === []) {
            return null;
        }

        $rankedItems = HomeFeedCollectionUtils::shuffleTopWindow(
            $rankedItems,
            $cardsPerBlock,
            self::SHUFFLE_WINDOW_MULTIPLIER,
            self::SHUFFLE_WINDOW_MIN
        );

        return new HomeFeedBlock(
            $context->isLoggedIn() ? 'neighbor-affinity' : 'anon-neighbor-affinity',
            'Parce que vous avez consulté',
            $rankedItems,
            true
        );
    }

    /**
     * @return int[]
     */
    private function resolveLoggedSeedIds(HomeFeedContext $context): array
    {
        $viewer = $context->getViewer();
        if ($viewer === null) {
            return [];
        }

        $seedIds = [];
        foreach ($this->presentationEventRepository->findRecentViewedPresentationIdsForUser($viewer, self::LOGGED_SEED_LIMIT) as $id) {
            $seedIds[$id] = true;
        }

        if (count($seedIds) < self::LOGGED_SEED_LIMIT) {
            $followLimit = self::LOGGED_SEED_LIMIT - count($seedIds);
            foreach ($this->followRepository->findLatestFollowedPresentations($viewer, $followLimit) as $presentation) {
                $presentationId = $presentation->getId();
                if ($presentationId !== null) {
                    $seedIds[$presentationId] = true;
                }
            }
        }

        if (count($seedIds) < self::LOGGED_SEED_LIMIT) {
            $bookmarkLimit = self::LOGGED_SEED_LIMIT - count($seedIds);
            /** @var Bookmark $bookmark */
            foreach ($this->bookmarkRepository->findLatestForUser($viewer, $bookmarkLimit) as $bookmark) {
                $presentationId = $bookmark->getProjectPresentation()?->getId();
                if ($presentationId !== null) {
                    $seedIds[$presentationId] = true;
                }
            }
        }

        return array_slice(array_keys($seedIds), 0, self::LOGGED_SEED_LIMIT);
    }
}
