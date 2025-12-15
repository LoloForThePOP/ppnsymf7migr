<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\PPBase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PPBase>
 */
class PPBaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PPBase::class);
    }

    /**
     * @return PPBase[]
     */
    public function findPublishedByCategories(array $categories, int $limit = 16): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = :notDeleted)')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC');

        if (!empty($categories)) {
            $qb->join('p.categories', 'c')
               ->andWhere('c.uniqueName IN (:cats)')
               ->setParameter('cats', $categories);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return PPBase[]
     */
    public function findLatestPublished(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return PPBase[] Returns an array of PPBase objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PPBase
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function remove(PPBase $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Returns likes/comments counts for the given PPBase ids.
     *
     * @param int[] $ids
     * @return array<int, array{likes:int, comments:int}>
     */
    public function getEngagementCountsForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $counts = array_fill_keys($ids, ['likes' => 0, 'comments' => 0]);

        $commentRows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.projectPresentation) AS id', 'COUNT(c.id) AS comments')
            ->from(Comment::class, 'c')
            ->where('c.projectPresentation IN (:ids)')
            ->groupBy('c.projectPresentation')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();

        foreach ($commentRows as $row) {
            $counts[(int) $row['id']]['comments'] = (int) $row['comments'];
        }

        $likeRows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(l.projectPresentation) AS id', 'COUNT(l.id) AS likes')
            ->from(Like::class, 'l')
            ->where('l.projectPresentation IN (:ids)')
            ->groupBy('l.projectPresentation')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();

        foreach ($likeRows as $row) {
            $counts[(int) $row['id']]['likes'] = (int) $row['likes'];
        }

        return $counts;
    }
}
