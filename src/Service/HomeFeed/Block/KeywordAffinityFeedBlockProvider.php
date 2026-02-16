<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedCollectionUtils;
use App\Service\HomeFeed\HomeFeedContext;
use App\Service\Recommendation\KeywordNormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 300)]
final class KeywordAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const PROFILE_LIMIT_LOGGED = 60;
    private const PROFILE_LIMIT_ANON = 16;
    private const CANDIDATE_FETCH_MULTIPLIER_LOGGED = 30;
    private const CANDIDATE_FETCH_MIN_LOGGED = 240;
    private const CANDIDATE_FETCH_MULTIPLIER_ANON = 60;
    private const CANDIDATE_FETCH_MIN_ANON = 480;
    private const KEYWORDS_PER_PRESENTATION_LIMIT = 12;
    private const MIN_PROFILE_KEYWORDS = 3;
    private const MIN_MATCHED_MIN = 6;
    private const ANON_WINDOW_MULTIPLIER = 10;
    private const ANON_WINDOW_MIN = 64;
    private const ANON_CORE_WINDOW_MULTIPLIER = 4;
    private const ANON_CORE_WINDOW_MIN = 24;
    private const QUERY_TERMS_LIMIT_LOGGED = 10;
    private const QUERY_TERMS_LIMIT_ANON = 8;
    private const KEYWORD_CANDIDATE_LIMIT_LOGGED = 1000;
    private const KEYWORD_CANDIDATE_LIMIT_ANON = 1200;
    private const MERGED_CANDIDATE_LIMIT = 1800;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
        private readonly KeywordNormalizer $keywordNormalizer,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $isLoggedIn = $context->isLoggedIn();
        $profileScores = $isLoggedIn
            ? $this->buildLoggedProfileScores($context)
            : $this->buildAnonProfileScores($context);

        if (count($profileScores) < self::MIN_PROFILE_KEYWORDS) {
            return null;
        }

        $fetchLimit = $this->resolveFetchLimit($context->getCardsPerBlock(), $isLoggedIn);

        $candidates = $this->resolveCandidates($context, $profileScores, $fetchLimit, $isLoggedIn);

        if ($candidates === []) {
            return null;
        }

        $rankedItems = $this->rankByKeywordOverlap(
            $candidates,
            $profileScores,
            $context->getCardsPerBlock(),
            $isLoggedIn
        );
        $minMatches = min($context->getCardsPerBlock(), self::MIN_MATCHED_MIN);
        if (count($rankedItems) < $minMatches) {
            return null;
        }

        return new HomeFeedBlock(
            $isLoggedIn ? 'domain-interest' : 'anon-domain-interest',
            'Domaines d’intérêt',
            $rankedItems,
            true
        );
    }

    /**
     * @param array<string,float> $profileScores
     *
     * @return PPBase[]
     */
    private function resolveCandidates(
        HomeFeedContext $context,
        array $profileScores,
        int $fetchLimit,
        bool $isLoggedIn
    ): array {
        $viewer = $context->getViewer();
        $recent = ($isLoggedIn && $viewer !== null)
            ? $this->ppBaseRepository->findLatestPublishedExcludingCreator($viewer, $fetchLimit)
            : $this->ppBaseRepository->findLatestPublished($fetchLimit);
        $queryTerms = $this->buildQueryTerms(
            $profileScores,
            $isLoggedIn ? self::QUERY_TERMS_LIMIT_LOGGED : self::QUERY_TERMS_LIMIT_ANON
        );
        if ($queryTerms === []) {
            return $recent;
        }

        $keywordPool = $this->ppBaseRepository->findPublishedByKeywordTerms(
            $queryTerms,
            $isLoggedIn ? self::KEYWORD_CANDIDATE_LIMIT_LOGGED : self::KEYWORD_CANDIDATE_LIMIT_ANON,
            $isLoggedIn ? $viewer : null
        );
        if ($keywordPool === []) {
            return $recent;
        }

        $merged = HomeFeedCollectionUtils::mergeUniquePresentations($keywordPool, $recent);

        return count($merged) > self::MERGED_CANDIDATE_LIMIT
            ? array_slice($merged, 0, self::MERGED_CANDIDATE_LIMIT)
            : $merged;
    }

    /**
     * @param array<string,float> $profileScores
     *
     * @return string[]
     */
    private function buildQueryTerms(array $profileScores, int $limit): array
    {
        if ($profileScores === []) {
            return [];
        }

        $limit = max(1, $limit);
        arsort($profileScores, SORT_NUMERIC);
        $terms = [];

        foreach (array_keys($profileScores) as $keyword) {
            $phrase = trim((string) $keyword);
            if ($phrase === '') {
                continue;
            }

            $phraseLength = function_exists('mb_strlen') ? mb_strlen($phrase) : strlen($phrase);
            if ($phraseLength >= 3) {
                $terms[$phrase] = true;
            }

            $parts = preg_split('/\s+/u', $phrase) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                $partLength = function_exists('mb_strlen') ? mb_strlen($part) : strlen($part);
                if ($part === '' || $partLength < 3) {
                    continue;
                }

                $terms[$part] = true;
                if (count($terms) >= $limit) {
                    break 2;
                }
            }

            if (count($terms) >= $limit) {
                break;
            }
        }

        return array_slice(array_keys($terms), 0, $limit);
    }

    private function resolveFetchLimit(int $cardsPerBlock, bool $isLoggedIn): int
    {
        if ($isLoggedIn) {
            return max(
                self::CANDIDATE_FETCH_MIN_LOGGED,
                $cardsPerBlock * self::CANDIDATE_FETCH_MULTIPLIER_LOGGED
            );
        }

        return max(
            self::CANDIDATE_FETCH_MIN_ANON,
            $cardsPerBlock * self::CANDIDATE_FETCH_MULTIPLIER_ANON
        );
    }

    /**
     * @return array<string,float>
     */
    private function buildLoggedProfileScores(HomeFeedContext $context): array
    {
        $viewer = $context->getViewer();
        if ($viewer === null) {
            return [];
        }

        $rawScores = $this->userPreferenceRepository->findTopKeywordScoresForUser($viewer, self::PROFILE_LIMIT_LOGGED);
        if ($rawScores === []) {
            return [];
        }

        $scores = [];
        foreach ($rawScores as $rawKeyword => $rawScore) {
            $keyword = $this->keywordNormalizer->normalizeKeyword((string) $rawKeyword);
            if ($keyword === null) {
                continue;
            }

            $score = max(0.0, (float) $rawScore);
            if ($score <= 0.0) {
                continue;
            }

            $scores[$keyword] = ($scores[$keyword] ?? 0.0) + $score;
        }

        return $scores;
    }

    /**
     * @return array<string,float>
     */
    private function buildAnonProfileScores(HomeFeedContext $context): array
    {
        $hints = array_slice($context->getAnonKeywordHints(), 0, self::PROFILE_LIMIT_ANON);
        if ($hints === []) {
            return [];
        }

        $scores = [];
        $rank = count($hints);

        foreach ($hints as $index => $rawHint) {
            $hint = str_replace(['_', '-'], ' ', (string) $rawHint);
            $keyword = $this->keywordNormalizer->normalizeKeyword($hint);
            if ($keyword === null) {
                continue;
            }

            $weight = max(1.0, (float) ($rank - $index));
            $scores[$keyword] = ($scores[$keyword] ?? 0.0) + $weight;
        }

        return $scores;
    }

    /**
     * @param PPBase[]             $candidates
     * @param array<string,float>  $profileScores
     *
     * @return PPBase[]
     */
    private function rankByKeywordOverlap(
        array $candidates,
        array $profileScores,
        int $cardsPerBlock,
        bool $isLoggedIn
    ): array
    {
        $rows = [];
        $now = new \DateTimeImmutable();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof PPBase) {
                continue;
            }

            $projectKeywords = $this->keywordNormalizer->normalizeRawKeywords(
                $candidate->getKeywords(),
                self::KEYWORDS_PER_PRESENTATION_LIMIT
            );

            if ($projectKeywords === []) {
                continue;
            }

            $overlapScore = 0.0;
            $matchedCount = 0;

            foreach ($projectKeywords as $keyword) {
                if (!array_key_exists($keyword, $profileScores)) {
                    continue;
                }

                $overlapScore += (float) $profileScores[$keyword];
                $matchedCount++;
            }

            if ($matchedCount === 0 || $overlapScore <= 0.0) {
                continue;
            }

            $ageSeconds = max(0, $now->getTimestamp() - $candidate->getCreatedAt()->getTimestamp());
            $ageDays = $ageSeconds / 86400.0;
            $freshnessBonus = exp(-$ageDays / 45.0) * 0.35;
            $matchBonus = min(0.5, $matchedCount * 0.1);

            $rows[] = [
                'item' => $candidate,
                'score' => $overlapScore + $freshnessBonus + $matchBonus,
            ];
        }

        if ($rows === []) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            $scoreCompare = $b['score'] <=> $a['score'];
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            /** @var PPBase $itemA */
            $itemA = $a['item'];
            /** @var PPBase $itemB */
            $itemB = $b['item'];

            return $itemB->getCreatedAt() <=> $itemA->getCreatedAt();
        });

        $ranked = array_map(static fn (array $row): PPBase => $row['item'], $rows);

        if ($isLoggedIn) {
            return $this->diversifyTopWindow($ranked, $cardsPerBlock);
        }

        return $this->diversifyAnonSelection($ranked, $cardsPerBlock);
    }

    /**
     * @param PPBase[] $items
     *
     * @return PPBase[]
     */
    private function diversifyTopWindow(array $items, int $cardsPerBlock): array
    {
        return HomeFeedCollectionUtils::shuffleTopWindow($items, $cardsPerBlock, 3, 24);
    }

    /**
     * Anonymous flow keeps a strong relevance core and rotates the rest from a wider window.
     *
     * @param PPBase[] $items
     *
     * @return PPBase[]
     */
    private function diversifyAnonSelection(array $items, int $cardsPerBlock): array
    {
        if (count($items) <= $cardsPerBlock) {
            return $items;
        }

        $window = min(
            count($items),
            max(self::ANON_WINDOW_MIN, $cardsPerBlock * self::ANON_WINDOW_MULTIPLIER)
        );
        $coreWindow = min($window, max(self::ANON_CORE_WINDOW_MIN, $cardsPerBlock * self::ANON_CORE_WINDOW_MULTIPLIER));

        $corePool = array_slice($items, 0, $coreWindow);
        $explorationPool = array_slice($items, $coreWindow, max(0, $window - $coreWindow));
        shuffle($corePool);
        shuffle($explorationPool);

        $coreTarget = min(count($corePool), (int) ceil($cardsPerBlock * 0.5));
        $selected = array_slice($corePool, 0, $coreTarget);

        $remaining = $cardsPerBlock - count($selected);
        if ($remaining > 0 && $explorationPool !== []) {
            $selected = array_merge($selected, array_slice($explorationPool, 0, $remaining));
        }

        $remaining = $cardsPerBlock - count($selected);
        if ($remaining > 0) {
            $coreRemainder = array_slice($corePool, $coreTarget);
            if ($coreRemainder !== []) {
                $selected = array_merge($selected, array_slice($coreRemainder, 0, $remaining));
            }
        }

        $remaining = $cardsPerBlock - count($selected);
        if ($remaining > 0) {
            $tail = array_slice($items, $window);
            if ($tail !== []) {
                shuffle($tail);
                $selected = array_merge($selected, array_slice($tail, 0, $remaining));
            }
        }

        shuffle($selected);

        return array_slice($selected, 0, $cardsPerBlock);
    }
}
