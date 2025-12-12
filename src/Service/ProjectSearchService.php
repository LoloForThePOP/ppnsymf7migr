<?php

namespace App\Service;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;

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
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $terms = array_filter(preg_split('/\s+/', $q) ?: [], fn ($t) => $t !== '');
        if (!$terms) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->where('p.isPublished = true')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = false)')
            ->setMaxResults($limit);

        $i = 0;
        foreach ($terms as $term) {
            $param = ':t' . $i;
            $qb->addSelect(
                '(
                    (CASE WHEN LOWER(p.title) LIKE LOWER(' . $param . ') THEN 3 ELSE 0 END) +
                    (CASE WHEN LOWER(p.goal) LIKE LOWER(' . $param . ') THEN 2 ELSE 0 END) +
                    (CASE WHEN LOWER(p.textDescription) LIKE LOWER(' . $param . ') THEN 1 ELSE 0 END) +
                    (CASE WHEN LOWER(p.keywords) LIKE LOWER(' . $param . ') THEN 1 ELSE 0 END)
                ) AS HIDDEN score_' . $i
            );
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.title) LIKE LOWER(' . $param . ')',
                    'LOWER(p.goal) LIKE LOWER(' . $param . ')',
                    'LOWER(p.textDescription) LIKE LOWER(' . $param . ')',
                    'LOWER(p.keywords) LIKE LOWER(' . $param . ')'
                )
            );
            $qb->setParameter($param, '%' . $term . '%');
            $i++;
        }

        $qb->orderBy('score_0', 'DESC')
           ->addOrderBy('p.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
