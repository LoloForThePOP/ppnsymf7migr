<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 330)]
final class TrendingFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const WINDOW_FETCH_MULTIPLIER = 8;
    private const WINDOW_FETCH_MIN = 60;
    private const WINDOW_OFFSETS = [0];
    private const MERGED_CANDIDATE_LIMIT = 700;
    private const FRESHNESS_DECAY_DAYS = 30.0;
    private const FRESHNESS_WEIGHT = 2.5;
    private const SHUFFLE_WINDOW_MULTIPLIER = 8;
    private const SHUFFLE_WINDOW_MIN = 56;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        #[Autowire('%app.home_feed.trending.enabled%')]
        private readonly bool $trendingEnabled,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        if (!$this->trendingEnabled) {
            return null;
        }

        $fetchLimit = max(self::WINDOW_FETCH_MIN, $context->getCardsPerBlock() * self::WINDOW_FETCH_MULTIPLIER);
        $candidates = $this->collectCandidates($fetchLimit, $context->getViewer());

        if ($candidates === []) {
            return null;
        }

        $candidateIds = array_values(array_filter(
            array_map(static fn (PPBase $item): ?int => $item->getId(), $candidates),
            static fn (?int $id): bool => $id !== null
        ));
        $engagement = $this->ppBaseRepository->getEngagementCountsForIds($candidateIds);
        $now = new \DateTimeImmutable();

        $rows = [];
        foreach ($candidates as $item) {
            $itemId = $item->getId();
            if ($itemId === null) {
                continue;
            }

            $likes = (int) ($engagement[$itemId]['likes'] ?? 0);
            $comments = (int) ($engagement[$itemId]['comments'] ?? 0);
            $views = max(0, (int) $item->getExtra()->getViewsCount());
            $createdAt = $item->getCreatedAt();
            $ageSeconds = max(0, $now->getTimestamp() - $createdAt->getTimestamp());
            $ageDays = $ageSeconds / 86400;

            $freshnessBoost = exp(-$ageDays / self::FRESHNESS_DECAY_DAYS) * self::FRESHNESS_WEIGHT;
            $score = ($likes * 1.0)
                + ($comments * 1.8)
                + (log(1.0 + $views) * 1.2)
                + $freshnessBoost;

            $rows[] = [
                'item' => $item,
                'score' => $score,
            ];
        }

        if ($rows === []) {
            return null;
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                $scoreDiff = $b['score'] <=> $a['score'];
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }

                /** @var PPBase $itemA */
                $itemA = $a['item'];
                /** @var PPBase $itemB */
                $itemB = $b['item'];

                return $itemB->getCreatedAt() <=> $itemA->getCreatedAt();
            }
        );

        $rankedItems = array_map(
            static fn (array $row): PPBase => $row['item'],
            $rows
        );
        $rankedItems = HomeFeedCollectionUtils::shuffleTopWindow(
            $rankedItems,
            $context->getCardsPerBlock(),
            self::SHUFFLE_WINDOW_MULTIPLIER,
            self::SHUFFLE_WINDOW_MIN
        );

        return new HomeFeedBlock(
            'trending',
            'Tendance sur Propon',
            $rankedItems,
            false
        );
    }

    /**
     * @return PPBase[]
     */
    private function collectCandidates(int $windowFetchLimit, ?User $excludeCreator): array
    {
        $batches = [];

        foreach (self::WINDOW_OFFSETS as $offset) {
            $batch = $this->ppBaseRepository->findLatestPublishedWindow($windowFetchLimit, $offset, $excludeCreator);
            if ($batch === []) {
                break;
            }

            $batches[] = $batch;
            if (count($batch) < $windowFetchLimit) {
                break;
            }
        }

        if ($batches === []) {
            return [];
        }

        $merged = HomeFeedCollectionUtils::mergeUniquePresentations(...$batches);

        return count($merged) > self::MERGED_CANDIDATE_LIMIT
            ? array_slice($merged, 0, self::MERGED_CANDIDATE_LIMIT)
            : $merged;
    }
}
