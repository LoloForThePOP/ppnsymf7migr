<?php

namespace App\Service\Recommendation;

use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\FollowRepository;
use App\Repository\PPBaseRepository;

final class RecommendationEngine
{
    private const DEFAULT_CONFIG = [
        'candidate_pool_limit' => 180,
        'max_per_category' => 2,
        'freshness_decay_days' => 120.0,
        'weights' => [
            'similarity' => [
                'category' => 0.75,
                'keyword' => 0.25,
            ],
            'personalized' => [
                'content' => 0.60,
                'engagement' => 0.25,
                'freshness' => 0.15,
            ],
            'non_personalized' => [
                'engagement' => 0.65,
                'freshness' => 0.35,
            ],
            'engagement_raw' => [
                'likes' => 1.0,
                'comments' => 1.4,
                'follows' => 1.8,
                'bookmarks' => 1.1,
                'views_log' => 0.6,
            ],
        ],
    ];

    private int $candidatePoolLimit;
    private int $maxPerCategory;
    private float $freshnessDecayDays;

    /**
     * @var array{category: float, keyword: float}
     */
    private array $similarityWeights;

    /**
     * @var array{content: float, engagement: float, freshness: float}
     */
    private array $personalizedWeights;

    /**
     * @var array{engagement: float, freshness: float}
     */
    private array $nonPersonalizedWeights;

    /**
     * @var array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float}
     */
    private array $engagementRawWeights;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly FollowRepository $followRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly array $homepageRecommendationConfig = [],
    ) {
        $resolved = $this->resolveConfig($this->homepageRecommendationConfig);
        $this->candidatePoolLimit = $resolved['candidate_pool_limit'];
        $this->maxPerCategory = $resolved['max_per_category'];
        $this->freshnessDecayDays = $resolved['freshness_decay_days'];
        $this->similarityWeights = $resolved['weights']['similarity'];
        $this->personalizedWeights = $resolved['weights']['personalized'];
        $this->nonPersonalizedWeights = $resolved['weights']['non_personalized'];
        $this->engagementRawWeights = $resolved['weights']['engagement_raw'];
    }

    /**
     * @param int[] $excludeProjectIds
     */
    public function recommendHomepage(?User $viewer, int $limit = 6, array $excludeProjectIds = []): RecommendationResult
    {
        $limit = max(1, min($limit, 24));
        $excludeMap = $this->toIntMap($excludeProjectIds);

        $candidates = $viewer
            ? $this->ppBaseRepository->findLatestPublishedExcludingCreator($viewer, $this->candidatePoolLimit)
            : $this->ppBaseRepository->findLatestPublished($this->candidatePoolLimit);

        if ($candidates === []) {
            return new RecommendationResult([], [], false);
        }

        $seed = $this->buildSeedProfile($viewer);
        $isPersonalized = $seed['categories'] !== [] || $seed['keywords'] !== [];

        $candidateIds = $this->extractCandidateIds($candidates, $excludeMap);
        if ($candidateIds === []) {
            return new RecommendationResult([], [], $isPersonalized);
        }

        $engagement = $this->ppBaseRepository->getEngagementCountsForIds($candidateIds);
        $followCounts = $this->followRepository->countByPresentationIds($candidateIds);
        $bookmarkCounts = $this->bookmarkRepository->countByPresentationIds($candidateIds);

        $rawScores = [];
        foreach ($candidates as $candidate) {
            $candidateId = $candidate->getId();
            if ($candidateId === null || isset($excludeMap[$candidateId])) {
                continue;
            }

            $categorySimilarity = $this->computeCategorySimilarity($candidate, $seed['categories']);
            $keywordSimilarity = $this->computeKeywordSimilarity($candidate, $seed['keywords']);
            $contentSimilarity = ($this->similarityWeights['category'] * $categorySimilarity)
                + ($this->similarityWeights['keyword'] * $keywordSimilarity);

            $likes = $engagement[$candidateId]['likes'] ?? 0;
            $comments = $engagement[$candidateId]['comments'] ?? 0;
            $follows = $followCounts[$candidateId] ?? 0;
            $bookmarks = $bookmarkCounts[$candidateId] ?? 0;
            $views = $candidate->getExtra()->getViewsCount();

            $rawScores[$candidateId] = [
                'item' => $candidate,
                'likes' => $likes,
                'comments' => $comments,
                'content' => $contentSimilarity,
                'engagementRaw' => $this->computeRawEngagement($likes, $comments, $follows, $bookmarks, $views),
                'freshness' => $this->computeFreshness($candidate),
            ];
        }

        if ($rawScores === []) {
            return new RecommendationResult([], [], $isPersonalized);
        }

        $maxEngagement = $this->computeMaxEngagement($rawScores);
        $ranked = [];
        foreach ($rawScores as $candidateId => $row) {
            $engagementScore = $maxEngagement > 0.0 ? $row['engagementRaw'] / $maxEngagement : 0.0;
            if ($isPersonalized) {
                $score = ($this->personalizedWeights['content'] * $row['content'])
                    + ($this->personalizedWeights['engagement'] * $engagementScore)
                    + ($this->personalizedWeights['freshness'] * $row['freshness']);
            } else {
                $score = ($this->nonPersonalizedWeights['engagement'] * $engagementScore)
                    + ($this->nonPersonalizedWeights['freshness'] * $row['freshness']);
            }

            $ranked[] = [
                'id' => $candidateId,
                'item' => $row['item'],
                'likes' => $row['likes'],
                'comments' => $row['comments'],
                'score' => $score,
            ];
        }

        usort(
            $ranked,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        $selectedRows = $this->selectWithDiversity($ranked, $limit);
        $items = [];
        $stats = [];
        foreach ($selectedRows as $row) {
            /** @var PPBase $item */
            $item = $row['item'];
            $itemId = $item->getId();
            if ($itemId === null) {
                continue;
            }
            $items[] = $item;
            $stats[$itemId] = [
                'likes' => (int) $row['likes'],
                'comments' => (int) $row['comments'],
            ];
        }

        return new RecommendationResult($items, $stats, $isPersonalized);
    }

    /**
     * @return array{categories: array<string,true>, keywords: array<string,true>}
     */
    private function buildSeedProfile(?User $viewer): array
    {
        if (!$viewer instanceof User) {
            return ['categories' => [], 'keywords' => []];
        }

        $seedProjects = $this->ppBaseRepository->findLatestByCreator($viewer, 12);
        $followed = $this->followRepository->findLatestFollowedPresentations($viewer, 24);
        $seedProjects = array_merge($seedProjects, $followed);

        $categories = [];
        $keywords = [];
        foreach ($seedProjects as $project) {
            foreach ($project->getCategories() as $category) {
                $key = $this->normalizeToken((string) $category->getUniqueName());
                if ($key !== '') {
                    $categories[$key] = true;
                }
            }

            foreach ($this->extractKeywords($project->getKeywords()) as $keyword) {
                $keywords[$keyword] = true;
            }
        }

        return [
            'categories' => $categories,
            'keywords' => $keywords,
        ];
    }

    /**
     * @param PPBase[] $candidates
     * @param array<int, true> $excludeMap
     *
     * @return int[]
     */
    private function extractCandidateIds(array $candidates, array $excludeMap): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            $candidateId = $candidate->getId();
            if ($candidateId === null || isset($excludeMap[$candidateId])) {
                continue;
            }
            $ids[] = $candidateId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, true> $seedCategories
     */
    private function computeCategorySimilarity(PPBase $candidate, array $seedCategories): float
    {
        if ($seedCategories === []) {
            return 0.0;
        }

        $candidateCategories = [];
        foreach ($candidate->getCategories() as $category) {
            $token = $this->normalizeToken((string) $category->getUniqueName());
            if ($token !== '') {
                $candidateCategories[$token] = true;
            }
        }

        if ($candidateCategories === []) {
            return 0.0;
        }

        $matches = 0;
        foreach (array_keys($candidateCategories) as $candidateCategory) {
            if (isset($seedCategories[$candidateCategory])) {
                $matches++;
            }
        }

        return $matches > 0 ? min(1.0, $matches / max(1, count($seedCategories))) : 0.0;
    }

    /**
     * @param array<string, true> $seedKeywords
     */
    private function computeKeywordSimilarity(PPBase $candidate, array $seedKeywords): float
    {
        if ($seedKeywords === []) {
            return 0.0;
        }

        $candidateKeywords = $this->extractKeywords($candidate->getKeywords());
        if ($candidateKeywords === []) {
            return 0.0;
        }

        $matches = 0;
        foreach (array_keys($candidateKeywords) as $keyword) {
            if (isset($seedKeywords[$keyword])) {
                $matches++;
            }
        }

        return $matches > 0 ? min(1.0, $matches / max(1, count($seedKeywords))) : 0.0;
    }

    private function computeRawEngagement(int $likes, int $comments, int $follows, int $bookmarks, int $views): float
    {
        $viewsBoost = log(1.0 + max(0, $views));

        return ($this->engagementRawWeights['likes'] * $likes)
            + ($this->engagementRawWeights['comments'] * $comments)
            + ($this->engagementRawWeights['follows'] * $follows)
            + ($this->engagementRawWeights['bookmarks'] * $bookmarks)
            + ($this->engagementRawWeights['views_log'] * $viewsBoost);
    }

    /**
     * @param array<int, array{engagementRaw: float}> $rows
     */
    private function computeMaxEngagement(array $rows): float
    {
        $max = 0.0;
        foreach ($rows as $row) {
            if ($row['engagementRaw'] > $max) {
                $max = $row['engagementRaw'];
            }
        }

        return $max;
    }

    private function computeFreshness(PPBase $candidate): float
    {
        $createdAt = $candidate->getCreatedAt();
        $now = new \DateTimeImmutable();
        $seconds = max(0, $now->getTimestamp() - $createdAt->getTimestamp());
        $days = $seconds / 86400;

        return exp(-$days / $this->freshnessDecayDays);
    }

    /**
     * @param array<int, array{id:int, item:PPBase, likes:int, comments:int, score:float}> $ranked
     *
     * @return array<int, array{id:int, item:PPBase, likes:int, comments:int, score:float}>
     */
    private function selectWithDiversity(array $ranked, int $limit): array
    {
        $selected = [];
        $deferred = [];
        $categoryCounts = [];

        foreach ($ranked as $row) {
            if (count($selected) >= $limit) {
                break;
            }

            $categories = $this->extractCandidateCategories($row['item']);
            if ($this->isWithinCategoryCap($categories, $categoryCounts)) {
                $selected[] = $row;
                foreach ($categories as $category) {
                    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
                }
            } else {
                $deferred[] = $row;
            }
        }

        if (count($selected) < $limit) {
            foreach ($deferred as $row) {
                if (count($selected) >= $limit) {
                    break;
                }
                $selected[] = $row;
            }
        }

        return $selected;
    }

    /**
     * @return string[]
     */
    private function extractCandidateCategories(PPBase $candidate): array
    {
        $categories = [];
        foreach ($candidate->getCategories() as $category) {
            $token = $this->normalizeToken((string) $category->getUniqueName());
            if ($token !== '') {
                $categories[$token] = true;
            }
        }

        return array_keys($categories);
    }

    /**
     * @param string[] $categories
     * @param array<string, int> $categoryCounts
     */
    private function isWithinCategoryCap(array $categories, array $categoryCounts): bool
    {
        if ($categories === []) {
            return true;
        }

        foreach ($categories as $category) {
            if (($categoryCounts[$category] ?? 0) < $this->maxPerCategory) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function extractKeywords(?string $keywords): array
    {
        if (!is_string($keywords) || trim($keywords) === '') {
            return [];
        }

        $chunks = preg_split('/[,;]+/u', $keywords) ?: [];
        $normalized = [];
        foreach ($chunks as $chunk) {
            $token = $this->normalizeToken($chunk);
            if ($token !== '') {
                $normalized[$token] = true;
            }
        }

        return $normalized;
    }

    private function normalizeToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value);
        } else {
            $value = strtolower($value);
        }

        return trim($value);
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, true>
     */
    private function toIntMap(array $ids): array
    {
        $map = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $map[$intId] = true;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{
     *   candidate_pool_limit: int,
     *   max_per_category: int,
     *   freshness_decay_days: float,
     *   weights: array{
     *     similarity: array{category: float, keyword: float},
     *     personalized: array{content: float, engagement: float, freshness: float},
     *     non_personalized: array{engagement: float, freshness: float},
     *     engagement_raw: array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float}
     *   }
     * }
     */
    private function resolveConfig(array $config): array
    {
        $defaults = self::DEFAULT_CONFIG;
        $weights = is_array($config['weights'] ?? null) ? $config['weights'] : [];

        $candidatePoolLimit = $this->toPositiveInt(
            $config['candidate_pool_limit'] ?? $defaults['candidate_pool_limit'],
            (int) $defaults['candidate_pool_limit']
        );
        $maxPerCategory = $this->toPositiveInt(
            $config['max_per_category'] ?? $defaults['max_per_category'],
            (int) $defaults['max_per_category']
        );
        $freshnessDecayDays = $this->toPositiveFloat(
            $config['freshness_decay_days'] ?? $defaults['freshness_decay_days'],
            (float) $defaults['freshness_decay_days']
        );

        /** @var array<string, mixed> $similarityInput */
        $similarityInput = is_array($weights['similarity'] ?? null) ? $weights['similarity'] : [];
        /** @var array<string, mixed> $personalizedInput */
        $personalizedInput = is_array($weights['personalized'] ?? null) ? $weights['personalized'] : [];
        /** @var array<string, mixed> $nonPersonalizedInput */
        $nonPersonalizedInput = is_array($weights['non_personalized'] ?? null) ? $weights['non_personalized'] : [];
        /** @var array<string, mixed> $engagementRawInput */
        $engagementRawInput = is_array($weights['engagement_raw'] ?? null) ? $weights['engagement_raw'] : [];

        /** @var array{category: float, keyword: float} $similarityDefaults */
        $similarityDefaults = $defaults['weights']['similarity'];
        /** @var array{content: float, engagement: float, freshness: float} $personalizedDefaults */
        $personalizedDefaults = $defaults['weights']['personalized'];
        /** @var array{engagement: float, freshness: float} $nonPersonalizedDefaults */
        $nonPersonalizedDefaults = $defaults['weights']['non_personalized'];
        /** @var array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float} $engagementRawDefaults */
        $engagementRawDefaults = $defaults['weights']['engagement_raw'];

        /** @var array{category: float, keyword: float} $similarity */
        $similarity = $this->normalizeLinearWeights($similarityInput, $similarityDefaults);
        /** @var array{content: float, engagement: float, freshness: float} $personalized */
        $personalized = $this->normalizeLinearWeights($personalizedInput, $personalizedDefaults);
        /** @var array{engagement: float, freshness: float} $nonPersonalized */
        $nonPersonalized = $this->normalizeLinearWeights($nonPersonalizedInput, $nonPersonalizedDefaults);
        /** @var array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float} $engagementRaw */
        $engagementRaw = $this->sanitizeRawEngagementWeights($engagementRawInput, $engagementRawDefaults);

        return [
            'candidate_pool_limit' => $candidatePoolLimit,
            'max_per_category' => $maxPerCategory,
            'freshness_decay_days' => $freshnessDecayDays,
            'weights' => [
                'similarity' => $similarity,
                'personalized' => $personalized,
                'non_personalized' => $nonPersonalized,
                'engagement_raw' => $engagementRaw,
            ],
        ];
    }

    /**
     * @template TKey of string
     *
     * @param array<string, mixed> $input
     * @param array<TKey, float> $defaults
     *
     * @return array<TKey, float>
     */
    private function normalizeLinearWeights(array $input, array $defaults): array
    {
        $weights = [];
        foreach ($defaults as $key => $default) {
            $weights[$key] = $this->toNonNegativeFloat($input[$key] ?? $default, $default);
        }

        $sum = 0.0;
        foreach ($weights as $weight) {
            $sum += $weight;
        }

        if ($sum <= 0.0) {
            return $defaults;
        }

        foreach ($weights as $key => $weight) {
            $weights[$key] = $weight / $sum;
        }

        return $weights;
    }

    /**
     * @param array<string, mixed> $input
     * @param array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float} $defaults
     *
     * @return array{likes: float, comments: float, follows: float, bookmarks: float, views_log: float}
     */
    private function sanitizeRawEngagementWeights(array $input, array $defaults): array
    {
        $weights = [
            'likes' => $this->toNonNegativeFloat($input['likes'] ?? $defaults['likes'], $defaults['likes']),
            'comments' => $this->toNonNegativeFloat($input['comments'] ?? $defaults['comments'], $defaults['comments']),
            'follows' => $this->toNonNegativeFloat($input['follows'] ?? $defaults['follows'], $defaults['follows']),
            'bookmarks' => $this->toNonNegativeFloat($input['bookmarks'] ?? $defaults['bookmarks'], $defaults['bookmarks']),
            'views_log' => $this->toNonNegativeFloat($input['views_log'] ?? $defaults['views_log'], $defaults['views_log']),
        ];

        $sum = 0.0;
        foreach ($weights as $weight) {
            $sum += $weight;
        }

        if ($sum <= 0.0) {
            return $defaults;
        }

        return $weights;
    }

    private function toPositiveInt(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $casted = (int) $value;

        return $casted > 0 ? $casted : $default;
    }

    private function toPositiveFloat(mixed $value, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $casted = (float) $value;

        return $casted > 0.0 ? $casted : $default;
    }

    private function toNonNegativeFloat(mixed $value, float $default): float
    {
        if (!is_numeric($value)) {
            return max(0.0, $default);
        }

        $casted = (float) $value;

        return $casted >= 0.0 ? $casted : max(0.0, $default);
    }
}
