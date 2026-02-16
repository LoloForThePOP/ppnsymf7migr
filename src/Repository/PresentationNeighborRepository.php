<?php

namespace App\Repository;

use App\Entity\PPBase;
use App\Entity\PresentationNeighbor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresentationNeighbor>
 */
class PresentationNeighborRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresentationNeighbor::class);
    }

    /**
     * Returns nearest neighbor projects for a source presentation.
     *
     * First tries the requested model, then falls back to any model if none exists.
     *
     * @return PPBase[]
     */
    public function findNeighborPresentations(PPBase $presentation, string $model, int $limit = 12): array
    {
        $limit = max(1, $limit);
        $neighbors = $this->fetchNeighborPresentations($presentation, $limit, trim($model));
        if ($neighbors !== []) {
            return $neighbors;
        }

        return $this->fetchNeighborPresentations($presentation, $limit, null);
    }

    /**
     * @return PPBase[]
     */
    public function findNeighborPresentationsById(int $presentationId, string $model, int $limit = 12): array
    {
        if ($presentationId <= 0) {
            return [];
        }

        /** @var PPBase $presentation */
        $presentation = $this->getEntityManager()->getReference(PPBase::class, $presentationId);

        return $this->findNeighborPresentations($presentation, $model, $limit);
    }

    /**
     * @return PPBase[]
     */
    private function fetchNeighborPresentations(PPBase $presentation, int $limit, ?string $model): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select('n', 'neighbor')
            ->join('n.neighbor', 'neighbor')
            ->where('n.presentation = :presentation')
            ->andWhere('neighbor != :presentation')
            ->andWhere('neighbor.isPublished = true')
            ->andWhere('(neighbor.isDeleted IS NULL OR neighbor.isDeleted = :notDeleted)')
            ->setParameter('presentation', $presentation)
            ->setParameter('notDeleted', false)
            ->setMaxResults($model === null ? max(20, $limit * 4) : $limit);

        if ($model !== null && $model !== '') {
            $qb->andWhere('n.model = :model')
                ->setParameter('model', $model)
                ->orderBy('n.rank', 'ASC')
                ->addOrderBy('n.score', 'DESC');
        } else {
            $qb->orderBy('n.updatedAt', 'DESC')
                ->addOrderBy('n.rank', 'ASC')
                ->addOrderBy('n.score', 'DESC');
        }

        /** @var PresentationNeighbor[] $rows */
        $rows = $qb->getQuery()->getResult();

        $selected = [];
        $selectedIds = [];
        foreach ($rows as $row) {
            $neighbor = $row->getNeighbor();
            $neighborId = $neighbor->getId();
            if ($neighborId === null || isset($selectedIds[$neighborId])) {
                continue;
            }

            $selectedIds[$neighborId] = true;
            $selected[] = $neighbor;

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }
}
