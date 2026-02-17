<?php

namespace App\Service\HomeFeed\Signal;

use App\Entity\Bookmark;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\FollowRepository;
use App\Repository\PPBaseRepository;
use App\Repository\PresentationEventRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\HomeFeed\HomeFeedContext;
use App\Service\Recommendation\KeywordNormalizer;

final class ViewerSignalProvider
{
    private const CATEGORY_LIMIT = 8;
    private const PROFILE_LIMIT_LOGGED = 60;
    private const PROFILE_LIMIT_ANON = 16;
    private const LOGGED_NEIGHBOR_SEED_LIMIT = 5;
    private const ANON_NEIGHBOR_SEED_LIMIT = 6;

    public function __construct(
        private readonly UserPreferenceRepository $userPreferenceRepository,
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly PresentationEventRepository $presentationEventRepository,
        private readonly FollowRepository $followRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly KeywordNormalizer $keywordNormalizer,
    ) {
    }

    public function resolveCategorySignals(HomeFeedContext $context): CategorySignals
    {
        if (!$context->isLoggedIn()) {
            return new CategorySignals($context->getAnonCategoryHints());
        }

        $viewer = $context->getViewer();
        if (!$viewer instanceof User) {
            return new CategorySignals([]);
        }

        $preferenceCategories = $this->userPreferenceRepository->findTopCategorySlugsForUser(
            $viewer,
            self::CATEGORY_LIMIT
        );
        if ($preferenceCategories !== []) {
            return new CategorySignals(
                primaryCategories: $preferenceCategories,
                primaryFromPreferences: true,
                fallbackCategories: $this->resolveCreatorCategories($viewer)
            );
        }

        return new CategorySignals($this->resolveCreatorCategories($viewer));
    }

    /**
     * @return array<string,float>
     */
    public function resolveKeywordProfileScores(HomeFeedContext $context): array
    {
        if ($context->isLoggedIn()) {
            $viewer = $context->getViewer();
            if (!$viewer instanceof User) {
                return [];
            }

            return $this->buildLoggedProfileScores($viewer);
        }

        return $this->buildAnonProfileScores($context);
    }

    /**
     * @return int[]
     */
    public function resolveNeighborSeedIds(HomeFeedContext $context): array
    {
        if (!$context->isLoggedIn()) {
            return array_slice($context->getAnonRecentViewIds(), 0, self::ANON_NEIGHBOR_SEED_LIMIT);
        }

        $viewer = $context->getViewer();
        if (!$viewer instanceof User) {
            return [];
        }

        $seedIds = [];
        foreach (
            $this->presentationEventRepository->findRecentViewedPresentationIdsForUser(
                $viewer,
                self::LOGGED_NEIGHBOR_SEED_LIMIT
            ) as $id
        ) {
            $seedIds[(int) $id] = true;
        }

        if (count($seedIds) < self::LOGGED_NEIGHBOR_SEED_LIMIT) {
            $followLimit = self::LOGGED_NEIGHBOR_SEED_LIMIT - count($seedIds);
            foreach ($this->followRepository->findLatestFollowedPresentations($viewer, $followLimit) as $presentation) {
                $presentationId = $presentation->getId();
                if ($presentationId !== null) {
                    $seedIds[$presentationId] = true;
                }
            }
        }

        if (count($seedIds) < self::LOGGED_NEIGHBOR_SEED_LIMIT) {
            $bookmarkLimit = self::LOGGED_NEIGHBOR_SEED_LIMIT - count($seedIds);
            /** @var Bookmark $bookmark */
            foreach ($this->bookmarkRepository->findLatestForUser($viewer, $bookmarkLimit) as $bookmark) {
                $presentationId = $bookmark->getProjectPresentation()?->getId();
                if ($presentationId !== null) {
                    $seedIds[$presentationId] = true;
                }
            }
        }

        return array_slice(array_keys($seedIds), 0, self::LOGGED_NEIGHBOR_SEED_LIMIT);
    }

    /**
     * @return string[]
     */
    private function resolveCreatorCategories(User $viewer): array
    {
        $creatorRecent = $this->ppBaseRepository->findLatestByCreator($viewer, 12);
        $categories = [];

        foreach ($creatorRecent as $project) {
            foreach ($project->getCategories() as $category) {
                $slug = trim((string) $category->getUniqueName());
                if ($slug !== '') {
                    $categories[$slug] = true;
                }
            }
        }

        return array_keys($categories);
    }

    /**
     * @return array<string,float>
     */
    private function buildLoggedProfileScores(User $viewer): array
    {
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
}

