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
     * Returns neighbors for multiple seed presentations in one query.
     *
     * @param int[] $presentationIds
     * @return array<int, PPBase[]> keyed by seed presentation id
     */
    public function findNeighborPresentationsForSeeds(
        array $presentationIds,
        string $model,
        int $limitPerSeed = 12
    ): array {
        $limitPerSeed = max(1, $limitPerSeed);
        $seedIds = [];
        foreach ($presentationIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $seedIds[$id] = true;
            }
        }

        if ($seedIds === []) {
            return [];
        }

        $neighborIdsBySeed = $this->findNeighborIdsForSeeds(
            array_keys($seedIds),
            trim($model),
            $limitPerSeed
        );
        if ($neighborIdsBySeed === []) {
            return [];
        }

        $allNeighborIds = [];
        foreach ($neighborIdsBySeed as $neighborIds) {
            foreach ($neighborIds as $neighborId) {
                $allNeighborIds[$neighborId] = true;
            }
        }

        $neighborsById = $this->fetchPublishedNeighborMap(array_keys($allNeighborIds));
        if ($neighborsById === []) {
            return [];
        }

        $neighborsBySeed = [];
        foreach ($neighborIdsBySeed as $seedId => $neighborIds) {
            foreach ($neighborIds as $neighborId) {
                if (!isset($neighborsById[$neighborId])) {
                    continue;
                }

                $neighborsBySeed[$seedId][] = $neighborsById[$neighborId];
            }
        }

        return $neighborsBySeed;
    }

    /**
     * @param int[] $seedIds
     *
     * @return array<int, int[]> keyed by seed id
     */
    private function findNeighborIdsForSeeds(array $seedIds, string $model, int $limitPerSeed): array
    {
        if ($seedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('n')
            ->select(
                'IDENTITY(n.presentation) AS seedId',
                'IDENTITY(n.neighbor) AS neighborId'
            )
            ->join('n.neighbor', 'neighbor')
            ->where('n.presentation IN (:presentationIds)')
            ->andWhere('neighbor.isPublished = true')
            ->andWhere('(neighbor.isDeleted IS NULL OR neighbor.isDeleted = :notDeleted)')
            ->setParameter('presentationIds', $seedIds)
            ->setParameter('notDeleted', false);

        if ($model !== '') {
            $qb->andWhere('n.model = :model')
                ->setParameter('model', $model)
                ->orderBy('n.presentation', 'ASC')
                ->addOrderBy('n.rank', 'ASC');
        } else {
            $qb->orderBy('n.presentation', 'ASC')
                ->addOrderBy('n.updatedAt', 'DESC')
                ->addOrderBy('n.rank', 'ASC')
                ->addOrderBy('n.score', 'DESC');
        }

        $rows = $qb->getQuery()->getArrayResult();

        $neighborIdsBySeed = [];
        $seenBySeed = [];

        foreach ($rows as $row) {
            $seedId = (int) ($row['seedId'] ?? 0);
            $neighborId = (int) ($row['neighborId'] ?? 0);
            if ($seedId <= 0 || $neighborId <= 0 || $seedId === $neighborId) {
                continue;
            }

            if (!isset($neighborIdsBySeed[$seedId])) {
                $neighborIdsBySeed[$seedId] = [];
                $seenBySeed[$seedId] = [];
            }

            if (isset($seenBySeed[$seedId][$neighborId])) {
                continue;
            }

            if (count($neighborIdsBySeed[$seedId]) >= $limitPerSeed) {
                continue;
            }

            $seenBySeed[$seedId][$neighborId] = true;
            $neighborIdsBySeed[$seedId][] = $neighborId;
        }

        return $neighborIdsBySeed;
    }

    /**
     * @param int[] $neighborIds
     *
     * @return array<int, PPBase>
     */
    private function fetchPublishedNeighborMap(array $neighborIds): array
    {
        if ($neighborIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->where('p.id IN (:ids)')
            ->andWhere('p.isPublished = :published')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = :notDeleted)')
            ->setParameter('ids', $neighborIds)
            ->setParameter('published', true)
            ->setParameter('notDeleted', false);

        /** @var PPBase[] $neighbors */
        $neighbors = $qb->getQuery()->getResult();
        $neighborsById = [];
        foreach ($neighbors as $neighbor) {
            $neighborId = $neighbor->getId();
            if ($neighborId === null) {
                continue;
            }

            $neighborsById[$neighborId] = $neighbor;
        }

        return $neighborsById;
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
                ->orderBy('n.rank', 'ASC');
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
