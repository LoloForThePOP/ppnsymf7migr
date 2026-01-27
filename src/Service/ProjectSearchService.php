<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Lightweight search service leveraging MySQL LIKE/prefix matching.
 */
class ProjectSearchService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return PPBase[]
     */
    public function search(string $query, int $limit = 20): array
    {
        $result = $this->searchWithFilters($query, [], $limit, 1);
        return $result['items'];
    }

    /**
     * @param string[] $categories
     * @param array{lat: float, lng: float, radius?: float}|null $location
     * @return array{
     *   items: PPBase[],
     *   total: int,
     *   page: int,
     *   pages: int,
     *   totalBase: int,
     *   categoryCounts: array<int, array{key: string, label: string, count: int}>
     * }
     */
    public function searchWithFilters(string $query, array $categories = [], int $limit = 20, int $page = 1, ?array $location = null): array
    {
        $q = trim($query);
        $location = $this->normalizeLocation($location);

        $terms = array_filter(preg_split('/\s+/', $q) ?: [], static fn ($t) => $t !== '');
        if (!$terms && !$location) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        $limit = max(1, $limit);
        $page = max(1, $page);
        $categories = array_values(array_filter(
            array_map(static fn ($value) => trim((string) $value), $categories),
            static fn ($value) => $value !== ''
        ));

        $baseCountQb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.id)')
            ->from(PPBase::class, 'p');
        $this->applyBaseFilters($baseCountQb, $terms, $location);
        $totalBase = (int) $baseCountQb->getQuery()->getSingleScalarResult();

        $categoryCounts = $totalBase > 0 ? $this->fetchCategoryCounts($terms, $location) : [];

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.id)')
            ->from(PPBase::class, 'p');

        $this->applyBaseFilters($countQb, $terms, $location);
        $this->applyCategoryFilter($countQb, $categories);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        if ($total === 0) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'pages' => 0,
                'totalBase' => $totalBase,
                'categoryCounts' => $categoryCounts,
            ];
        }

        $pages = (int) ceil($total / $limit);
        if ($page > $pages) {
            $page = $pages;
            return ['items' => [], 'total' => $total, 'page' => $page, 'pages' => $pages];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT p')
            ->from(PPBase::class, 'p')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $this->applyBaseFilters($qb, $terms, $location);
        $this->applyCategoryFilter($qb, $categories);
        if ($terms) {
            $this->addScoringSelects($qb, $terms);
            $qb->orderBy('score_0', 'DESC')
               ->addOrderBy('p.createdAt', 'DESC');
        } else {
            $qb->orderBy('p.createdAt', 'DESC');
        }

        return [
            'items' => $qb->getQuery()->getResult(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'totalBase' => $totalBase,
            'categoryCounts' => $categoryCounts,
        ];
    }

    /**
     * @param string[] $terms
     * @param string[] $categories
     */
    private function applyBaseFilters(QueryBuilder $qb, array $terms, ?array $location): void
    {
        $qb->where('p.isPublished = true')
           ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = false)');

        if ($terms) {
            foreach ($terms as $i => $term) {
                $param = ':t' . $i;
                $qb->andWhere(
                    $qb->expr()->orX(
                        'LOWER(p.title) LIKE LOWER(' . $param . ')',
                        'LOWER(p.goal) LIKE LOWER(' . $param . ')',
                        'LOWER(p.textDescription) LIKE LOWER(' . $param . ')',
                        'LOWER(p.keywords) LIKE LOWER(' . $param . ')'
                    )
                );
                $qb->setParameter($param, '%' . $term . '%');
            }
        }

        if ($location) {
            $bbox = $this->computeBoundingBox($location['lat'], $location['lng'], $location['radius']);
            $qb->join('p.places', 'pl')
               ->andWhere('pl.geoloc.latitude BETWEEN :minLat AND :maxLat')
               ->andWhere('pl.geoloc.longitude BETWEEN :minLng AND :maxLng')
               ->setParameter('minLat', $bbox['minLat'])
               ->setParameter('maxLat', $bbox['maxLat'])
               ->setParameter('minLng', $bbox['minLng'])
               ->setParameter('maxLng', $bbox['maxLng']);
        }
    }

    /**
     * @param string[] $categories
     */
    private function applyCategoryFilter(QueryBuilder $qb, array $categories): void
    {
        if ($categories === []) {
            return;
        }

        foreach (array_values($categories) as $index => $category) {
            $alias = 'c' . $index;
            $param = 'cat' . $index;
            $qb->join('p.categories', $alias, 'WITH', $alias . '.uniqueName = :' . $param)
               ->setParameter($param, $category);
        }
    }

    /**
     * @param string[] $terms
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function fetchCategoryCounts(array $terms, ?array $location): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c.uniqueName AS uniqueName')
            ->addSelect('COALESCE(c.label, c.uniqueName) AS label')
            ->addSelect('COUNT(DISTINCT p.id) AS count')
            ->from(Category::class, 'c')
            ->join('c.projectPresentation', 'p');

        $this->applyBaseFilters($qb, $terms, $location);

        $rows = $qb->groupBy('c.uniqueName, c.label')
            ->orderBy('count', 'DESC')
            ->addOrderBy('c.label', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static function (array $row): array {
            return [
                'key' => (string) $row['uniqueName'],
                'label' => (string) $row['label'],
                'count' => (int) $row['count'],
            ];
        }, $rows));
    }
    /**
     * @param string[] $terms
     */
    private function addScoringSelects(QueryBuilder $qb, array $terms): void
    {
        foreach ($terms as $i => $_term) {
            $param = ':t' . $i;
            $qb->addSelect(
                '(
                    (CASE WHEN LOWER(p.title) LIKE LOWER(' . $param . ') THEN 3 ELSE 0 END) +
                    (CASE WHEN LOWER(p.goal) LIKE LOWER(' . $param . ') THEN 2 ELSE 0 END) +
                    (CASE WHEN LOWER(p.textDescription) LIKE LOWER(' . $param . ') THEN 1 ELSE 0 END) +
                    (CASE WHEN LOWER(p.keywords) LIKE LOWER(' . $param . ') THEN 1 ELSE 0 END)
                ) AS HIDDEN score_' . $i
            );
        }
    }

    /**
     * @param array{lat: mixed, lng: mixed, radius?: mixed}|null $location
     * @return array{lat: float, lng: float, radius: float}|null
     */
    private function normalizeLocation(?array $location): ?array
    {
        if (!$location) {
            return null;
        }
        $lat = $location['lat'] ?? $location['latitude'] ?? null;
        $lng = $location['lng'] ?? $location['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        $radius = $location['radius'] ?? 10;
        if (!is_numeric($radius)) {
            $radius = 10;
        }
        $radius = max(1.0, min(200.0, (float) $radius));

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'radius' => $radius,
        ];
    }

    /**
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    private function computeBoundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $earthRadiusKm = 6371.0;
        $latRad = deg2rad($lat);
        $latDelta = rad2deg($radiusKm / $earthRadiusKm);
        $lngDelta = rad2deg($radiusKm / ($earthRadiusKm * max(0.0001, cos($latRad))));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }
}
