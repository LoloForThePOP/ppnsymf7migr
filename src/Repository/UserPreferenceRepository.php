<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPreference>
 */
class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    /**
     * @return string[]
     */
    public function findTopCategorySlugsForUser(User $user, int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $preference = $this->findOneBy(['user' => $user]);
        if (!$preference instanceof UserPreference) {
            return [];
        }

        $scored = $preference->getFavCategories();
        if ($scored === []) {
            return [];
        }

        arsort($scored, SORT_NUMERIC);
        $slugs = [];

        foreach ($scored as $slug => $score) {
            if (!is_string($slug) || !preg_match('/^[a-z0-9_-]{1,40}$/', $slug)) {
                continue;
            }

            if (!is_numeric($score) || (float) $score <= 0.0) {
                continue;
            }

            $slugs[] = $slug;
            if (count($slugs) >= $limit) {
                break;
            }
        }

        return $slugs;
    }

    /**
     * @return array<string,float>
     */
    public function findTopKeywordScoresForUser(User $user, int $limit = 40): array
    {
        $limit = max(1, min($limit, 120));
        $preference = $this->findOneBy(['user' => $user]);
        if (!$preference instanceof UserPreference) {
            return [];
        }

        $scored = $preference->getFavKeywords();
        if ($scored === []) {
            return [];
        }

        arsort($scored, SORT_NUMERIC);
        $keywords = [];

        foreach ($scored as $keyword => $score) {
            if (!is_string($keyword) || !is_numeric($score) || (float) $score <= 0.0) {
                continue;
            }

            $keywords[$keyword] = (float) $score;
            if (count($keywords) >= $limit) {
                break;
            }
        }

        return $keywords;
    }
}
