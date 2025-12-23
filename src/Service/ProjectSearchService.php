<?php

namespace App\Service;

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
     * @return array{items: PPBase[], total: int, page: int, pages: int}
     */
    public function searchWithFilters(string $query, array $categories = [], int $limit = 20, int $page = 1): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        $terms = array_filter(preg_split('/\s+/', $q) ?: [], static fn ($t) => $t !== '');
        if (!$terms) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        $limit = max(1, $limit);
        $page = max(1, $page);
        $categories = array_values(array_filter(
            array_map(static fn ($value) => trim((string) $value), $categories),
            static fn ($value) => $value !== ''
        ));

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.id)')
            ->from(PPBase::class, 'p');

        $this->applySearchFilters($countQb, $terms, $categories);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        if ($total === 0) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 0];
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

        $this->applySearchFilters($qb, $terms, $categories);
        $this->addScoringSelects($qb, $terms);

        $qb->orderBy('score_0', 'DESC')
           ->addOrderBy('p.createdAt', 'DESC');

        return [
            'items' => $qb->getQuery()->getResult(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @param string[] $terms
     * @param string[] $categories
     */
    private function applySearchFilters(QueryBuilder $qb, array $terms, array $categories): void
    {
        $qb->where('p.isPublished = true')
           ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = false)');

        if ($categories !== []) {
            $qb->join('p.categories', 'c')
               ->andWhere('c.uniqueName IN (:cats)')
               ->setParameter('cats', $categories);
        }

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
}
