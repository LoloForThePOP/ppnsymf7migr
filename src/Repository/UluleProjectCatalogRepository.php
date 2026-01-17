<?php

namespace App\Repository;

use App\Entity\UluleProjectCatalog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UluleProjectCatalog>
 */
class UluleProjectCatalogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UluleProjectCatalog::class);
    }

    public function findOneByUluleId(int $ululeId): ?UluleProjectCatalog
    {
        return $this->findOneBy(['ululeId' => $ululeId]);
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.importStatus AS status, COUNT(u.id) AS total')
            ->groupBy('u.importStatus')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            UluleProjectCatalog::STATUS_PENDING => 0,
            UluleProjectCatalog::STATUS_IMPORTED => 0,
            UluleProjectCatalog::STATUS_SKIPPED => 0,
            UluleProjectCatalog::STATUS_FAILED => 0,
            'unknown' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'] ?? null;
            $count = (int) ($row['total'] ?? 0);
            if ($status === null || $status === '') {
                $counts['unknown'] += $count;
            } elseif (array_key_exists($status, $counts)) {
                $counts[$status] += $count;
            } else {
                $counts['unknown'] += $count;
            }
        }

        return $counts;
    }
}
