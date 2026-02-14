<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 220)]
final class TrendingFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $fetchLimit = max(180, $context->getCardsPerBlock() * 24);
        $candidates = $this->ppBaseRepository->findLatestPublished($fetchLimit);
        if ($candidates === []) {
            return null;
        }

        $viewer = $context->getViewer();
        if ($viewer !== null) {
            $viewerId = $viewer->getId();
            $candidates = array_values(array_filter(
                $candidates,
                static fn (PPBase $item): bool => $item->getCreator()?->getId() !== $viewerId
            ));
        }

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

            $freshnessBoost = exp(-$ageDays / 14.0) * 5.0;
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

        return new HomeFeedBlock(
            'trending',
            'Tendance sur Propon',
            $rankedItems,
            false
        );
    }
}

