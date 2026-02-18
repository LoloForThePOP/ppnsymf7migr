<?php

namespace App\Service\HomeFeed\Block;

use App\Repository\PresentationNeighborRepository;
use App\Service\AI\PresentationEmbeddingService;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use App\Service\HomeFeed\Signal\ViewerSignalProvider;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 360)]
final class NeighborAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const PER_SEED_NEIGHBOR_LIMIT_MULTIPLIER = 1;
    private const PER_SEED_NEIGHBOR_LIMIT_MIN = 8;
    private const MIN_UNIQUE_RESULTS = 6;
    private const SHUFFLE_WINDOW_MULTIPLIER = 6;
    private const SHUFFLE_WINDOW_MIN = 36;
    private const MAX_RANKED_RESULTS = 96;

    public function __construct(
        private readonly PresentationNeighborRepository $presentationNeighborRepository,
        private readonly PresentationEmbeddingService $presentationEmbeddingService,
        private readonly ViewerSignalProvider $viewerSignalProvider,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $seedIds = $this->viewerSignalProvider->resolveNeighborSeedIds($context);

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
        $neighborsBySeed = $this->presentationNeighborRepository->findNeighborPresentationsForSeeds(
            $seedIds,
            $model,
            $perSeedLimit
        );

        $missingSeedIds = array_values(array_filter(
            $seedIds,
            static fn (int $seedId): bool => !isset($neighborsBySeed[$seedId])
        ));
        if ($missingSeedIds !== []) {
            $fallbackNeighborsBySeed = $this->presentationNeighborRepository->findNeighborPresentationsForSeeds(
                $missingSeedIds,
                '',
                $perSeedLimit
            );
            foreach ($fallbackNeighborsBySeed as $seedId => $neighbors) {
                if (!isset($neighborsBySeed[$seedId])) {
                    $neighborsBySeed[$seedId] = $neighbors;
                }
            }
        }

        foreach ($seedIds as $seedIndex => $seedId) {
            $neighbors = $neighborsBySeed[$seedId] ?? [];
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
        $rankedIds = array_slice(array_keys($scoresByPresentation), 0, self::MAX_RANKED_RESULTS);
        foreach ($rankedIds as $presentationId) {
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
            $this->resolveBlockTitle($context->isLoggedIn(), $seedCount),
            $rankedItems,
            true
        );
    }

    private function resolveBlockTitle(bool $isLoggedIn, int $seedCount): string
    {
        if ($seedCount >= 3) {
            return $isLoggedIn
                ? 'Dans la continuité de vos consultations'
                : 'Inspiré de vos dernières consultations';
        }

        if ($seedCount === 2) {
            return $isLoggedIn
                ? 'Basé sur vos consultations récentes'
                : 'Selon vos consultations récentes';
        }

        // Legacy fallback kept for very low-signal cases.
        return 'Parce que vous avez consulté';
    }
}
