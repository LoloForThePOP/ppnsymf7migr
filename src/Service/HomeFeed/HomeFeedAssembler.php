<?php

namespace App\Service\HomeFeed;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\Block\KeywordAffinityFeedBlockProvider;
use App\Service\HomeFeed\Diagnostics\HomeFeedDiagnosticsCollectorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class HomeFeedAssembler
{
    private const NEIGHBOR_BLOCK_KEYS = [
        'neighbor-affinity',
        'anon-neighbor-affinity',
    ];

    /**
     * @var array{
     *   enabled: bool,
     *   totals: array{durationMs:float,queryCount:int,queryMs:float},
     *   entries: array<int,array{
     *     provider:string,
     *     providerClass:string,
     *     blockKey:string,
     *     blockTitle:string,
     *     rawItems:int,
     *     selectedItems:int,
     *     accepted:bool,
     *     durationMs:float,
     *     queryCount:int,
     *     queryMs:float
     *   }>
     * }
     */
    private array $lastDiagnostics = [
        'enabled' => false,
        'totals' => [
            'durationMs' => 0.0,
            'queryCount' => 0,
            'queryMs' => 0.0,
        ],
        'entries' => [],
    ];

    /**
     * @param iterable<HomeFeedBlockProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.home_feed_block_provider')]
        private readonly iterable $providers,
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly HomeFeedDiagnosticsCollectorInterface $diagnosticsCollector,
        #[Autowire('%app.home_feed.keyword_affinity.enabled%')]
        private readonly bool $keywordAffinityEnabled,
        #[Autowire('%app.home_feed.keyword_affinity.neighbor_min_items_for_skip%')]
        private readonly int $keywordAffinityNeighborMinItems,
    ) {
    }

    /**
     * @return HomeFeedBlock[]
     */
    public function build(HomeFeedContext $context): array
    {
        $buildStartedAt = microtime(true);
        $buildStartSnapshot = $this->snapshotDiagnostics();
        $this->lastDiagnostics = [
            'enabled' => $this->diagnosticsCollector->isEnabled(),
            'totals' => [
                'durationMs' => 0.0,
                'queryCount' => 0,
                'queryMs' => 0.0,
            ],
            'entries' => [],
        ];

        $preparedBlocks = [];
        $excludedProjectIds = [];
        $allProjectIds = [];
        $neighborSelectedItems = 0;

        foreach ($this->providers as $provider) {
            $providerStartedAt = microtime(true);
            $providerStartSnapshot = $this->snapshotDiagnostics();

            if ($this->shouldSkipKeywordProvider($provider, $neighborSelectedItems)) {
                $providerEndSnapshot = $this->snapshotDiagnostics();
                $this->appendDiagnosticsEntry(
                    provider: $provider,
                    block: null,
                    rawItemsCount: 0,
                    selectedItemsCount: 0,
                    accepted: false,
                    durationMs: $this->durationMs($providerStartedAt),
                    beforeSnapshot: $providerStartSnapshot,
                    afterSnapshot: $providerEndSnapshot
                );
                continue;
            }

            $rawBlock = $provider->provide($context);
            $rawItemsCount = $rawBlock instanceof HomeFeedBlock ? count($rawBlock->getItems()) : 0;
            $selectedItemsCount = 0;
            $accepted = false;

            if ($rawBlock instanceof HomeFeedBlock) {
                $items = $this->dedupeItems(
                    $rawBlock->getItems(),
                    $excludedProjectIds,
                    $context->getCardsPerBlock(),
                    $context->isCreatorCapEnabled(),
                    $context->getCreatorCapPerBlock()
                );
                $selectedItemsCount = count($items);

                if ($items !== []) {
                    $accepted = true;
                    $preparedBlocks[] = [
                        'block' => $rawBlock,
                        'items' => $items,
                    ];

                    if ($this->isNeighborBlockKey($rawBlock->getKey())) {
                        $neighborSelectedItems = max($neighborSelectedItems, $selectedItemsCount);
                    }

                    foreach ($items as $item) {
                        $itemId = $item->getId();
                        if ($itemId !== null) {
                            $allProjectIds[$itemId] = true;
                        }
                    }
                }
            }

            $providerEndSnapshot = $this->snapshotDiagnostics();
            $this->appendDiagnosticsEntry(
                provider: $provider,
                block: $rawBlock,
                rawItemsCount: $rawItemsCount,
                selectedItemsCount: $selectedItemsCount,
                accepted: $accepted,
                durationMs: $this->durationMs($providerStartedAt),
                beforeSnapshot: $providerStartSnapshot,
                afterSnapshot: $providerEndSnapshot
            );

            if (count($preparedBlocks) >= $context->getMaxBlocks()) {
                break;
            }
        }

        $postStartedAt = microtime(true);
        $postStartSnapshot = $this->snapshotDiagnostics();

        if ($preparedBlocks === []) {
            $postEndSnapshot = $this->snapshotDiagnostics();
            $this->appendGlobalDiagnosticsEntry(
                label: 'post-processing',
                durationMs: $this->durationMs($postStartedAt),
                beforeSnapshot: $postStartSnapshot,
                afterSnapshot: $postEndSnapshot
            );
            $this->finalizeDiagnostics($buildStartedAt, $buildStartSnapshot);

            return [];
        }

        $allIds = array_keys($allProjectIds);
        if ($allIds !== []) {
            $this->ppBaseRepository->warmupCategoriesForIds($allIds);
        }

        $allStats = $allIds === []
            ? []
            : $this->ppBaseRepository->getEngagementCountsForIds($allIds);

        $postEndSnapshot = $this->snapshotDiagnostics();
        $this->appendGlobalDiagnosticsEntry(
            label: 'post-processing',
            durationMs: $this->durationMs($postStartedAt),
            beforeSnapshot: $postStartSnapshot,
            afterSnapshot: $postEndSnapshot
        );

        $blocks = [];
        foreach ($preparedBlocks as $entry) {
            /** @var HomeFeedBlock $block */
            $block = $entry['block'];
            /** @var PPBase[] $items */
            $items = $entry['items'];

            $stats = [];
            foreach ($items as $item) {
                $itemId = $item->getId();
                if ($itemId === null) {
                    continue;
                }

                $stats[$itemId] = $allStats[$itemId] ?? ['likes' => 0, 'comments' => 0];
            }

            $blocks[] = $block->withItemsAndStats($items, $stats);
        }

        $this->finalizeDiagnostics($buildStartedAt, $buildStartSnapshot);

        return $blocks;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   totals: array{durationMs:float,queryCount:int,queryMs:float},
     *   entries: array<int,array{
     *     provider:string,
     *     providerClass:string,
     *     blockKey:string,
     *     blockTitle:string,
     *     rawItems:int,
     *     selectedItems:int,
     *     accepted:bool,
     *     durationMs:float,
     *     queryCount:int,
     *     queryMs:float
     *   }>
     * }
     */
    public function getLastDiagnostics(): array
    {
        return $this->lastDiagnostics;
    }

    /**
     * @param PPBase[] $items
     * @param array<int,true> $excludedProjectIds
     *
     * @return PPBase[]
     */
    private function dedupeItems(
        array $items,
        array &$excludedProjectIds,
        int $limit,
        bool $creatorCapEnabled,
        int $creatorCapPerBlock
    ): array
    {
        $selected = [];
        $creatorCounts = [];

        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $projectId = $item->getId();
            if ($projectId === null || isset($excludedProjectIds[$projectId])) {
                continue;
            }

            if ($creatorCapEnabled) {
                $creatorId = $item->getCreator()?->getId();
                if ($creatorId !== null && ($creatorCounts[$creatorId] ?? 0) >= $creatorCapPerBlock) {
                    continue;
                }
            }

            $excludedProjectIds[$projectId] = true;
            $selected[] = $item;

            if ($creatorCapEnabled) {
                $creatorId = $item->getCreator()?->getId();
                if ($creatorId !== null) {
                    $creatorCounts[$creatorId] = ($creatorCounts[$creatorId] ?? 0) + 1;
                }
            }

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    /**
     * @return array{queryCount:int,queryMs:float}
     */
    private function snapshotDiagnostics(): array
    {
        return $this->diagnosticsCollector->snapshot();
    }

    /**
     * @param array{queryCount:int,queryMs:float} $beforeSnapshot
     * @param array{queryCount:int,queryMs:float} $afterSnapshot
     */
    private function appendDiagnosticsEntry(
        object $provider,
        ?HomeFeedBlock $block,
        int $rawItemsCount,
        int $selectedItemsCount,
        bool $accepted,
        float $durationMs,
        array $beforeSnapshot,
        array $afterSnapshot
    ): void {
        $queryCount = max(0, (int) ($afterSnapshot['queryCount'] - $beforeSnapshot['queryCount']));
        $queryMs = max(0.0, (float) ($afterSnapshot['queryMs'] - $beforeSnapshot['queryMs']));

        $this->lastDiagnostics['entries'][] = [
            'provider' => $this->shortClassName($provider),
            'providerClass' => $provider::class,
            'blockKey' => $block?->getKey() ?? '',
            'blockTitle' => $block?->getTitle() ?? '',
            'rawItems' => max(0, $rawItemsCount),
            'selectedItems' => max(0, $selectedItemsCount),
            'accepted' => $accepted,
            'durationMs' => round($durationMs, 2),
            'queryCount' => $queryCount,
            'queryMs' => round($queryMs, 3),
        ];
    }

    /**
     * @param array{queryCount:int,queryMs:float} $beforeSnapshot
     * @param array{queryCount:int,queryMs:float} $afterSnapshot
     */
    private function appendGlobalDiagnosticsEntry(
        string $label,
        float $durationMs,
        array $beforeSnapshot,
        array $afterSnapshot
    ): void {
        $queryCount = max(0, (int) ($afterSnapshot['queryCount'] - $beforeSnapshot['queryCount']));
        $queryMs = max(0.0, (float) ($afterSnapshot['queryMs'] - $beforeSnapshot['queryMs']));

        $this->lastDiagnostics['entries'][] = [
            'provider' => 'HomeFeedAssembler',
            'providerClass' => self::class,
            'blockKey' => $label,
            'blockTitle' => $label,
            'rawItems' => 0,
            'selectedItems' => 0,
            'accepted' => true,
            'durationMs' => round($durationMs, 2),
            'queryCount' => $queryCount,
            'queryMs' => round($queryMs, 3),
        ];
    }

    /**
     * @param array{queryCount:int,queryMs:float} $buildStartSnapshot
     */
    private function finalizeDiagnostics(float $buildStartedAt, array $buildStartSnapshot): void
    {
        $buildEndSnapshot = $this->snapshotDiagnostics();
        $this->lastDiagnostics['totals'] = [
            'durationMs' => $this->durationMs($buildStartedAt),
            'queryCount' => max(0, (int) ($buildEndSnapshot['queryCount'] - $buildStartSnapshot['queryCount'])),
            'queryMs' => round(max(0.0, (float) ($buildEndSnapshot['queryMs'] - $buildStartSnapshot['queryMs'])), 3),
        ];
    }

    private function shortClassName(object $instance): string
    {
        $fqcn = $instance::class;
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }

    private function shouldSkipKeywordProvider(object $provider, int $neighborSelectedItems): bool
    {
        if (!$this->isKeywordProvider($provider)) {
            return false;
        }

        if (!$this->keywordAffinityEnabled) {
            return true;
        }

        return $neighborSelectedItems >= max(1, $this->keywordAffinityNeighborMinItems);
    }

    private function isNeighborBlockKey(string $blockKey): bool
    {
        return in_array($blockKey, self::NEIGHBOR_BLOCK_KEYS, true);
    }

    private function isKeywordProvider(object $provider): bool
    {
        return $provider instanceof KeywordAffinityFeedBlockProvider;
    }
}
