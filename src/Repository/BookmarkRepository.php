<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bookmark>
 */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    /**
     * @return Bookmark[]
     */
    public function findLatestForUser(User $user, int $limit = 200): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.projectPresentation', 'p')
            ->addSelect('p')
            ->andWhere('b.user = :user')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('user', $user)
            ->setParameter('notDeleted', false)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $presentationIds
     *
     * @return array<int, int>
     */
    public function countByPresentationIds(array $presentationIds): array
    {
        if ($presentationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.projectPresentation) AS id', 'COUNT(b.id) AS total')
            ->andWhere('b.projectPresentation IN (:ids)')
            ->groupBy('b.projectPresentation')
            ->setParameter('ids', $presentationIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }
}
