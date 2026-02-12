<?php

namespace App\Repository;

use App\Entity\PresentationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresentationEvent>
 */
class PresentationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresentationEvent::class);
    }

    public function countByType(string $type, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.type = :type')
            ->andWhere('e.createdAt BETWEEN :start AND :end')
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string,int>
     */
    public function countByTypeGroupedByDay(string $type, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DATE(created_at) AS day, COUNT(*) AS total
                FROM presentation_event
                WHERE type = :type AND created_at BETWEEN :start AND :end
                GROUP BY day';
        $startParam = $start->format('Y-m-d H:i:s');
        $endParam = $end->format('Y-m-d H:i:s');
        $rows = $conn->executeQuery($sql, [
            'type' => $type,
            'start' => $startParam,
            'end' => $endParam,
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $day = (string) $row['day'];
            $counts[$day] = (int) $row['total'];
        }

        return $counts;
    }

    public function countDistinctVisitors(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.visitorHash)')
            ->andWhere('e.type = :type')
            ->andWhere('e.visitorHash IS NOT NULL')
            ->andWhere('e.createdAt BETWEEN :start AND :end')
            ->setParameter('type', PresentationEvent::TYPE_VIEW)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countReturningVisitors(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.visitorHash AS visitor', 'COUNT(e.id) AS total')
            ->andWhere('e.type = :type')
            ->andWhere('e.visitorHash IS NOT NULL')
            ->andWhere('e.createdAt BETWEEN :start AND :end')
            ->groupBy('e.visitorHash')
            ->having('COUNT(e.id) > 1')
            ->setParameter('type', PresentationEvent::TYPE_VIEW)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        return count($rows);
    }

    /**
     * Returns event counts grouped by recommendation placement extracted from `meta`.
     *
     * @return array<string,int>
     */
    public function countRecommendationByPlacement(string $type, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.meta AS meta')
            ->andWhere('e.type = :type')
            ->andWhere('e.createdAt BETWEEN :start AND :end')
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $meta = $this->normalizeMeta($row['meta'] ?? null);
            $placement = $meta['placement'] ?? null;
            if (!is_string($placement) || trim($placement) === '') {
                continue;
            }
            $placement = strtolower(trim($placement));
            $counts[$placement] = ($counts[$placement] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            try {
                $decoded = json_decode($meta, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
