<?php

namespace App\Service\Recommendation;

use App\Entity\Bookmark;
use App\Entity\Follow;
use App\Entity\Like;
use App\Entity\PPBase;
use App\Entity\PresentationEvent;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserPreferenceUpdater
{
    private const MAX_INTERACTIONS_PER_SIGNAL = 600;
    private const MAX_STORED_CATEGORIES = 40;
    private const MAX_STORED_KEYWORDS = 120;
    private const KEYWORDS_PER_PRESENTATION_LIMIT = 12;
    private const VIEW_LOOKBACK_DAYS = 90;

    private const WEIGHT_LIKE = 3.0;
    private const WEIGHT_FOLLOW = 4.0;
    private const WEIGHT_BOOKMARK = 2.5;
    private const WEIGHT_VIEW = 0.9;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    public function recomputeForUser(User $user, bool $flush = true): UserPreference
    {
        $categoryScores = [];
        $keywordScores = [];

        $this->accumulateFromInteractions(
            $this->loadInteractions(Like::class, 'l', $user),
            self::WEIGHT_LIKE,
            $categoryScores,
            $keywordScores
        );

        $this->accumulateFromInteractions(
            $this->loadInteractions(Follow::class, 'f', $user),
            self::WEIGHT_FOLLOW,
            $categoryScores,
            $keywordScores
        );

        $this->accumulateFromInteractions(
            $this->loadInteractions(Bookmark::class, 'b', $user),
            self::WEIGHT_BOOKMARK,
            $categoryScores,
            $keywordScores
        );

        $this->accumulateFromViews($user, $categoryScores, $keywordScores);

        $categoryScores = $this->sortAndTruncate($categoryScores, self::MAX_STORED_CATEGORIES);
        $keywordScores = $this->sortAndTruncate($keywordScores, self::MAX_STORED_KEYWORDS);

        $preference = $this->userPreferenceRepository->findOneBy(['user' => $user]);
        if (!$preference instanceof UserPreference) {
            $preference = new UserPreference($user);
            $this->entityManager->persist($preference);
        }

        $preference
            ->setFavCategories($categoryScores)
            ->setFavKeywords($keywordScores)
            ->refreshUpdatedAt();

        if ($flush) {
            $this->entityManager->flush();
        }

        return $preference;
    }

    /**
     * @param array<string,float> $categoryScores
     * @param array<string,float> $keywordScores
     */
    private function accumulateFromViews(User $user, array &$categoryScores, array &$keywordScores): void
    {
        $since = (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('P%dD', self::VIEW_LOOKBACK_DAYS)));

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.projectPresentation) AS projectId', 'COUNT(e.id) AS viewCount')
            ->from(PresentationEvent::class, 'e')
            ->where('e.user = :user')
            ->andWhere('e.type = :type')
            ->andWhere('e.createdAt >= :since')
            ->groupBy('e.projectPresentation')
            ->setParameter('user', $user)
            ->setParameter('type', PresentationEvent::TYPE_VIEW)
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return;
        }

        $viewCounts = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['projectId'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $viewCounts[$projectId] = (int) ($row['viewCount'] ?? 0);
        }

        if ($viewCounts === []) {
            return;
        }

        $presentations = $this->entityManager->createQueryBuilder()
            ->select('p', 'c')
            ->from(PPBase::class, 'p')
            ->leftJoin('p.categories', 'c')
            ->where('p.id IN (:ids)')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = false')
            ->setParameter('ids', array_keys($viewCounts))
            ->getQuery()
            ->getResult();

        foreach ($presentations as $presentation) {
            if (!$presentation instanceof PPBase) {
                continue;
            }

            $presentationId = $presentation->getId();
            if ($presentationId === null) {
                continue;
            }

            $viewCount = max(0, (int) ($viewCounts[$presentationId] ?? 0));
            if ($viewCount === 0) {
                continue;
            }

            $weight = self::WEIGHT_VIEW * min(4.0, sqrt((float) $viewCount));
            $this->accumulateFromPresentation($presentation, $weight, $categoryScores, $keywordScores);
        }
    }

    /**
     * @param Like[]|Follow[]|Bookmark[] $interactions
     * @param array<string,float> $categoryScores
     * @param array<string,float> $keywordScores
     */
    private function accumulateFromInteractions(
        array $interactions,
        float $weight,
        array &$categoryScores,
        array &$keywordScores
    ): void {
        foreach ($interactions as $interaction) {
            $presentation = $interaction->getProjectPresentation();
            if (!$presentation instanceof PPBase) {
                continue;
            }

            $this->accumulateFromPresentation($presentation, $weight, $categoryScores, $keywordScores);
        }
    }

    /**
     * @param array<string,float> $categoryScores
     * @param array<string,float> $keywordScores
     */
    private function accumulateFromPresentation(
        PPBase $presentation,
        float $weight,
        array &$categoryScores,
        array &$keywordScores
    ): void {
        if ($weight <= 0.0) {
            return;
        }

        foreach ($presentation->getCategories() as $category) {
            $slug = strtolower(trim((string) $category->getUniqueName()));
            if ($slug === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $slug)) {
                continue;
            }

            $categoryScores[$slug] = ($categoryScores[$slug] ?? 0.0) + $weight;
        }

        $keywords = $this->extractKeywords($presentation->getKeywords());
        foreach ($keywords as $keyword) {
            $keywordScores[$keyword] = ($keywordScores[$keyword] ?? 0.0) + $weight;
        }
    }

    /**
     * @return Like[]|Follow[]|Bookmark[]
     */
    private function loadInteractions(string $entityClass, string $alias, User $user): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias, 'p', 'c')
            ->from($entityClass, $alias)
            ->innerJoin(sprintf('%s.projectPresentation', $alias), 'p')
            ->leftJoin('p.categories', 'c')
            ->where(sprintf('%s.user = :user', $alias))
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = false')
            ->setParameter('user', $user)
            ->orderBy(sprintf('%s.createdAt', $alias), 'DESC')
            ->setMaxResults(self::MAX_INTERACTIONS_PER_SIGNAL)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    private function extractKeywords(?string $rawKeywords): array
    {
        $rawKeywords = trim((string) $rawKeywords);
        if ($rawKeywords === '') {
            return [];
        }

        $parts = preg_split('/[,;|]+/', $rawKeywords) ?: [];
        $keywords = [];

        foreach ($parts as $part) {
            $keyword = mb_strtolower(trim($part));
            if ($keyword === '') {
                continue;
            }

            $keyword = preg_replace('/\s+/', ' ', $keyword) ?? '';
            if ($keyword === '') {
                continue;
            }

            if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 60) {
                continue;
            }

            if (!preg_match('/^[\p{L}\p{N}\s\-_]+$/u', $keyword)) {
                continue;
            }

            $keywords[$keyword] = true;
            if (count($keywords) >= self::KEYWORDS_PER_PRESENTATION_LIMIT) {
                break;
            }
        }

        return array_keys($keywords);
    }

    /**
     * @param array<string,float> $scores
     *
     * @return array<string,float>
     */
    private function sortAndTruncate(array $scores, int $limit): array
    {
        if ($scores === []) {
            return [];
        }

        arsort($scores, SORT_NUMERIC);
        $scores = array_slice($scores, 0, max(1, $limit), true);

        foreach ($scores as $key => $value) {
            $scores[$key] = round((float) $value, 4);
        }

        return $scores;
    }
}
