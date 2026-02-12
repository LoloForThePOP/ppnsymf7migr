<?php

namespace App\Repository;

use App\Entity\Follow;
use App\Entity\PPBase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follow>
 */
class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    public function findLatestFollowedPresentations(User $user, int $limit = 6): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->innerJoin(Follow::class, 'f', 'WITH', 'f.projectPresentation = p')
            ->andWhere('f.user = :user')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->andWhere('p.creator != :user')
            ->setParameter('user', $user)
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->orderBy('f.createdAt', 'DESC')
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

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(f.projectPresentation) AS id', 'COUNT(f.id) AS total')
            ->from(Follow::class, 'f')
            ->where('f.projectPresentation IN (:ids)')
            ->groupBy('f.projectPresentation')
            ->setParameter('ids', $presentationIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }

    //    /**
    //     * @return Follow[] Returns an array of Follow objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Follow
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
