<?php

namespace App\Service\Recommendation;

use App\Entity\PPBase;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserCategoryPreferenceSignalUpdater
{
    private const MAX_STORED_CATEGORIES = 40;
    private const MAX_CATEGORY_SCORE = 500.0;
    private const MIN_RETAINED_SCORE = 0.05;
    private const DECAY_HALF_LIFE_DAYS = 60.0;

    // Kept aligned with UserPreferenceUpdater interaction weights.
    private const WEIGHT_LIKE = 3.0;
    private const WEIGHT_FOLLOW = 4.0;
    private const WEIGHT_BOOKMARK = 2.5;
    private const WEIGHT_VIEW = 0.9;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    public function onLike(User $user, PPBase $presentation, bool $added, bool $flush = true): void
    {
        $this->applyProjectSignal($user, $presentation, $added ? self::WEIGHT_LIKE : -self::WEIGHT_LIKE, $flush);
    }

    public function onFollow(User $user, PPBase $presentation, bool $added, bool $flush = true): void
    {
        $this->applyProjectSignal($user, $presentation, $added ? self::WEIGHT_FOLLOW : -self::WEIGHT_FOLLOW, $flush);
    }

    public function onBookmark(User $user, PPBase $presentation, bool $added, bool $flush = true): void
    {
        $this->applyProjectSignal(
            $user,
            $presentation,
            $added ? self::WEIGHT_BOOKMARK : -self::WEIGHT_BOOKMARK,
            $flush
        );
    }

    public function onView(User $user, PPBase $presentation, bool $flush = true): void
    {
        $this->applyProjectSignal($user, $presentation, self::WEIGHT_VIEW, $flush);
    }

    private function applyProjectSignal(User $user, PPBase $presentation, float $weight, bool $flush): void
    {
        if ($weight === 0.0) {
            return;
        }

        $categorySlugs = $this->extractCategorySlugs($presentation);
        if ($categorySlugs === []) {
            return;
        }

        $preference = $this->userPreferenceRepository->findOneBy(['user' => $user]);
        if (!$preference instanceof UserPreference) {
            $preference = new UserPreference($user);
            $this->entityManager->persist($preference);
        }

        $scores = $this->applyDecay($preference->getFavCategories(), $preference->getUpdatedAt());
        $deltaPerCategory = $weight / max(1, count($categorySlugs));

        foreach ($categorySlugs as $slug) {
            $next = ((float) ($scores[$slug] ?? 0.0)) + $deltaPerCategory;

            if ($next < self::MIN_RETAINED_SCORE) {
                unset($scores[$slug]);
                continue;
            }

            $scores[$slug] = min(self::MAX_CATEGORY_SCORE, $next);
        }

        $scores = $this->sortAndTruncate($scores);

        $preference
            ->setFavCategories($scores)
            ->refreshUpdatedAt();

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string,float> $scores
     *
     * @return array<string,float>
     */
    private function applyDecay(array $scores, \DateTimeImmutable $updatedAt): array
    {
        if ($scores === []) {
            return [];
        }

        $elapsedSeconds = max(0, time() - $updatedAt->getTimestamp());
        if ($elapsedSeconds <= 0) {
            return $scores;
        }

        $elapsedDays = $elapsedSeconds / 86400.0;
        if ($elapsedDays < 1.0) {
            return $scores;
        }

        $decayFactor = pow(0.5, $elapsedDays / self::DECAY_HALF_LIFE_DAYS);
        if ($decayFactor >= 0.999) {
            return $scores;
        }

        $decayed = [];
        foreach ($scores as $slug => $value) {
            if (!is_string($slug) || !is_numeric($value)) {
                continue;
            }

            $score = (float) $value * $decayFactor;
            if ($score >= self::MIN_RETAINED_SCORE) {
                $decayed[$slug] = $score;
            }
        }

        return $decayed;
    }

    /**
     * @param array<string,float> $scores
     *
     * @return array<string,float>
     */
    private function sortAndTruncate(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        foreach ($scores as $slug => $score) {
            if (!preg_match('/^[a-z0-9_-]{1,40}$/', (string) $slug) || !is_numeric($score) || (float) $score <= 0.0) {
                unset($scores[$slug]);
                continue;
            }

            $scores[$slug] = round((float) $score, 4);
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores, SORT_NUMERIC);

        return array_slice($scores, 0, self::MAX_STORED_CATEGORIES, true);
    }

    /**
     * @return string[]
     */
    private function extractCategorySlugs(PPBase $presentation): array
    {
        $slugs = [];

        foreach ($presentation->getCategories() as $category) {
            $slug = strtolower(trim((string) $category->getUniqueName()));
            if ($slug === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $slug)) {
                continue;
            }

            $slugs[$slug] = true;
        }

        return array_keys($slugs);
    }
}
