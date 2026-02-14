<?php

namespace App\Service\HomeFeed;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class HomeFeedAssembler
{
    /**
     * @param iterable<HomeFeedBlockProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.home_feed_block_provider')]
        private readonly iterable $providers,
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    /**
     * @return HomeFeedBlock[]
     */
    public function build(HomeFeedContext $context): array
    {
        $blocks = [];
        $excludedProjectIds = [];

        foreach ($this->providers as $provider) {
            $rawBlock = $provider->provide($context);
            if (!$rawBlock instanceof HomeFeedBlock) {
                continue;
            }

            $items = $this->dedupeItems($rawBlock->getItems(), $excludedProjectIds, $context->getCardsPerBlock());
            if ($items === []) {
                continue;
            }

            $ids = array_values(array_filter(
                array_map(static fn (PPBase $item): ?int => $item->getId(), $items),
                static fn (?int $id): bool => $id !== null
            ));
            $stats = $this->ppBaseRepository->getEngagementCountsForIds($ids);
            $blocks[] = $rawBlock->withItemsAndStats($items, $stats);

            if (count($blocks) >= $context->getMaxBlocks()) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * @param PPBase[] $items
     * @param array<int,true> $excludedProjectIds
     *
     * @return PPBase[]
     */
    private function dedupeItems(array $items, array &$excludedProjectIds, int $limit): array
    {
        $selected = [];

        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $projectId = $item->getId();
            if ($projectId === null || isset($excludedProjectIds[$projectId])) {
                continue;
            }

            $excludedProjectIds[$projectId] = true;
            $selected[] = $item;

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }
}

