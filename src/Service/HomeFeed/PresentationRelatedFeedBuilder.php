<?php

namespace App\Service\HomeFeed;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use App\Repository\PresentationNeighborRepository;
use App\Service\AI\PresentationEmbeddingService;
use App\Service\HomeFeed\Block\CategoryAffinityFeedBlockProvider;
use App\Service\HomeFeed\Block\KeywordAffinityFeedBlockProvider;
use App\Service\Recommendation\KeywordNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PresentationRelatedFeedBuilder
{
    private const MAX_BLOCKS = 2;
    private const KEYWORD_HINT_LIMIT = 16;
    private const SIMILAR_BLOCK_KEY = 'related-neighbors';
    private const SIMILAR_BLOCK_TITLE = 'Projets similaires';

    public function __construct(
        private readonly CategoryAffinityFeedBlockProvider $categoryAffinityProvider,
        private readonly KeywordAffinityFeedBlockProvider $keywordAffinityProvider,
        private readonly KeywordNormalizer $keywordNormalizer,
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly PresentationNeighborRepository $presentationNeighborRepository,
        private readonly PresentationEmbeddingService $presentationEmbeddingService,
        #[Autowire('%app.home_feed.cards_per_block%')]
        private readonly int $cardsPerBlock,
    ) {
    }

    /**
     * @return HomeFeedBlock[]
     */
    public function buildForPresentation(PPBase $presentation): array
    {
        $blocks = [];
        $currentId = $presentation->getId();
        $excluded = $currentId !== null ? [$currentId => true] : [];

        $neighborItems = $this->presentationNeighborRepository->findNeighborPresentations(
            $presentation,
            $this->presentationEmbeddingService->getModel(),
            $this->cardsPerBlock
        );
        $neighborItems = $this->dedupeAndLimitItems($neighborItems, $excluded, $this->cardsPerBlock);
        if ($neighborItems !== []) {
            $blocks[] = new HomeFeedBlock(
                self::SIMILAR_BLOCK_KEY,
                self::SIMILAR_BLOCK_TITLE,
                $neighborItems,
                false,
                $this->ppBaseRepository->getEngagementCountsForIds($this->extractItemIds($neighborItems))
            );
        }

        if (count($blocks) >= self::MAX_BLOCKS) {
            return $blocks;
        }

        $categoryHints = $this->extractCategoryHints($presentation);
        $keywordHints = $this->keywordNormalizer->normalizeRawKeywords(
            $presentation->getKeywords(),
            self::KEYWORD_HINT_LIMIT
        );

        if ($categoryHints === [] && $keywordHints === []) {
            return $blocks;
        }

        $context = new HomeFeedContext(
            viewer: null,
            cardsPerBlock: $this->cardsPerBlock,
            maxBlocks: self::MAX_BLOCKS,
            anonCategoryHints: $categoryHints,
            anonKeywordHints: $keywordHints,
            locationHint: null,
            creatorCapEnabled: false,
            creatorCapPerBlock: 2
        );

        foreach ([$this->categoryAffinityProvider, $this->keywordAffinityProvider] as $provider) {
            if (count($blocks) >= self::MAX_BLOCKS) {
                break;
            }

            $rawBlock = $provider->provide($context);
            if (!$rawBlock instanceof HomeFeedBlock) {
                continue;
            }

            $items = $this->dedupeAndLimitItems($rawBlock->getItems(), $excluded, $context->getCardsPerBlock());
            if ($items === []) {
                continue;
            }

            $stats = $this->ppBaseRepository->getEngagementCountsForIds($this->extractItemIds($items));
            $blocks[] = new HomeFeedBlock(
                $this->mapBlockKey($rawBlock->getKey()),
                $this->mapBlockTitle($rawBlock->getKey(), $rawBlock->getTitle()),
                $items,
                false,
                $stats
            );
        }

        return $blocks;
    }

    /**
     * @return string[]
     */
    private function extractCategoryHints(PPBase $presentation): array
    {
        $hints = [];
        foreach ($presentation->getCategories() as $category) {
            $slug = strtolower(trim((string) $category->getUniqueName()));
            if ($slug === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $slug)) {
                continue;
            }

            $hints[$slug] = true;
        }

        return array_keys($hints);
    }

    /**
     * @param PPBase[] $items
     * @param array<int,true> $excluded
     *
     * @return PPBase[]
     */
    private function dedupeAndLimitItems(array $items, array &$excluded, int $limit): array
    {
        $selected = [];

        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $itemId = $item->getId();
            if ($itemId === null || isset($excluded[$itemId])) {
                continue;
            }

            $excluded[$itemId] = true;
            $selected[] = $item;
            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param PPBase[] $items
     *
     * @return int[]
     */
    private function extractItemIds(array $items): array
    {
        return array_values(array_filter(
            array_map(static fn (PPBase $item): ?int => $item->getId(), $items),
            static fn (?int $id): bool => $id !== null
        ));
    }

    private function mapBlockKey(string $rawKey): string
    {
        return match ($rawKey) {
            'anon-category-affinity' => 'related-by-categories',
            'anon-domain-interest' => 'related-by-keywords',
            default => $rawKey,
        };
    }

    private function mapBlockTitle(string $rawKey, string $fallbackTitle): string
    {
        return match ($rawKey) {
            'anon-category-affinity' => 'Dans les mêmes catégories',
            'anon-domain-interest' => 'Domaines proches',
            default => $fallbackTitle,
        };
    }
}
