<?php

namespace App\Repository;

use App\Entity\User;
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
     * @return int[]
     */
    public function findRecentViewedPresentationIdsForUser(User $user, int $limit = 12): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.projectPresentation) AS presentation_id', 'MAX(e.createdAt) AS HIDDEN lastSeen')
            ->andWhere('e.user = :user')
            ->andWhere('e.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', PresentationEvent::TYPE_VIEW)
            ->groupBy('e.projectPresentation')
            ->orderBy('lastSeen', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['presentation_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<int,array{block:string,impressions:int,clicks:int,ctr:float}>
     */
    public function getHomepageFeedMetricsByBlock(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sql = <<<SQL
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.block')) AS block_key,
                SUM(CASE WHEN type = :impressionType THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN type = :clickType THEN 1 ELSE 0 END) AS clicks
            FROM presentation_event
            WHERE created_at BETWEEN :start AND :end
              AND (type = :impressionType OR type = :clickType)
              AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.placement')) = :placement
            GROUP BY block_key
            HAVING block_key IS NOT NULL AND block_key <> ''
            ORDER BY impressions DESC, clicks DESC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            [
                'impressionType' => PresentationEvent::TYPE_HOME_FEED_IMPRESSION,
                'clickType' => PresentationEvent::TYPE_HOME_FEED_CLICK,
                'placement' => 'homepage',
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ]
        )->fetchAllAssociative();

        $metrics = [];
        foreach ($rows as $row) {
            $block = trim((string) ($row['block_key'] ?? ''));
            if ($block === '') {
                continue;
            }

            $impressions = max(0, (int) ($row['impressions'] ?? 0));
            $clicks = max(0, (int) ($row['clicks'] ?? 0));
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

            $metrics[] = [
                'block' => $block,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
            ];
        }

        return $metrics;
    }

}
