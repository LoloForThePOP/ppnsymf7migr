<?php

namespace App\Service\HomeFeed\Block;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedBlock;
use App\Service\HomeFeed\HomeFeedBlockProviderInterface;
use App\Service\HomeFeed\HomeFeedContext;
use App\Service\Recommendation\KeywordNormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 300)]
final class KeywordAffinityFeedBlockProvider implements HomeFeedBlockProviderInterface
{
    private const PROFILE_LIMIT_LOGGED = 60;
    private const PROFILE_LIMIT_ANON = 16;
    private const CANDIDATE_FETCH_MULTIPLIER = 30;
    private const CANDIDATE_FETCH_MIN = 240;
    private const KEYWORDS_PER_PRESENTATION_LIMIT = 12;
    private const MIN_PROFILE_KEYWORDS = 3;
    private const MIN_MATCHED_MIN = 6;

    public function __construct(
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
        private readonly KeywordNormalizer $keywordNormalizer,
    ) {
    }

    public function provide(HomeFeedContext $context): ?HomeFeedBlock
    {
        $profileScores = $context->isLoggedIn()
            ? $this->buildLoggedProfileScores($context)
            : $this->buildAnonProfileScores($context);

        if (count($profileScores) < self::MIN_PROFILE_KEYWORDS) {
            return null;
        }

        $fetchLimit = max(
            self::CANDIDATE_FETCH_MIN,
            $context->getCardsPerBlock() * self::CANDIDATE_FETCH_MULTIPLIER
        );

        $viewer = $context->getViewer();
        $candidates = $viewer
            ? $this->ppBaseRepository->findLatestPublishedExcludingCreator($viewer, $fetchLimit)
            : $this->ppBaseRepository->findLatestPublished($fetchLimit);

        if ($candidates === []) {
            return null;
        }

        $rankedItems = $this->rankByKeywordOverlap($candidates, $profileScores, $context->getCardsPerBlock());
        $minMatches = min($context->getCardsPerBlock(), self::MIN_MATCHED_MIN);
        if (count($rankedItems) < $minMatches) {
            return null;
        }

        return new HomeFeedBlock(
            $context->isLoggedIn() ? 'domain-interest' : 'anon-domain-interest',
            'Domaines d’intérêt',
            $rankedItems,
            true
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
    private function rankByKeywordOverlap(array $candidates, array $profileScores, int $cardsPerBlock): array
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

        return $this->diversifyTopWindow($ranked, $cardsPerBlock);
    }

    /**
     * @param PPBase[] $items
     *
     * @return PPBase[]
     */
    private function diversifyTopWindow(array $items, int $cardsPerBlock): array
    {
        if (count($items) <= $cardsPerBlock) {
            return $items;
        }

        $window = min(count($items), max(24, $cardsPerBlock * 3));
        $top = array_slice($items, 0, $window);
        shuffle($top);

        return array_merge($top, array_slice($items, $window));
    }
}
