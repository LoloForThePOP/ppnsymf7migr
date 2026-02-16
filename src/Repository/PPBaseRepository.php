<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\PPBase;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
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
        return $this->findPublishedByCategoriesWindow($categories, $limit);
    }

    /**
     * @param string[] $categories
     *
     * @return PPBase[]
     */
    public function findPublishedByCategoriesWindow(
        array $categories,
        int $limit = 16,
        int $offset = 0,
        ?User $excludeCreator = null
    ): array {
        if ($categories === []) {
            return $this->findLatestPublishedWindow($limit, $offset, $excludeCreator);
        }

        $ids = $this->findPublishedIdsByCategoriesWindow($categories, $limit, $offset, $excludeCreator);
        if ($ids === []) {
            return [];
        }

        return $this->findPublishedByIdsPreserveOrder($ids);
    }

    /**
     * @param int[] $ids
     *
     * @return PPBase[]
     */
    public function findPublishedByIdsPreserveOrder(array $ids): array
    {
        $orderedIds = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0 || isset($orderedIds[$id])) {
                continue;
            }

            $orderedIds[$id] = true;
        }

        if ($orderedIds === []) {
            return [];
        }

        $normalizedIds = array_keys($orderedIds);
        $items = $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :notDeleted')
            ->setParameter('ids', $normalizedIds)
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->getQuery()
            ->getResult();

        $itemsById = [];
        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $itemId = $item->getId();
            if ($itemId === null) {
                continue;
            }

            $itemsById[$itemId] = $item;
        }

        $orderedItems = [];
        foreach ($normalizedIds as $id) {
            if (!isset($itemsById[$id])) {
                continue;
            }

            $orderedItems[] = $itemsById[$id];
        }

        return $orderedItems;
    }

    /**
     * @param string[] $terms
     *
     * @return PPBase[]
     */
    public function findPublishedByKeywordTerms(array $terms, int $limit = 300, ?User $excludeCreator = null): array
    {
        $normalizedTerms = [];
        foreach ($terms as $term) {
            $token = trim((string) $term);
            if ($token === '') {
                continue;
            }

            $token = function_exists('mb_strtolower') ? mb_strtolower($token) : strtolower($token);
            $tokenLength = function_exists('mb_strlen') ? mb_strlen($token) : strlen($token);
            if ($tokenLength < 3) {
                continue;
            }

            $normalizedTerms[$token] = true;
        }

        if ($normalizedTerms === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :notDeleted')
            ->andWhere('p.keywords IS NOT NULL')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeCreator instanceof User) {
            $qb->andWhere('p.creator != :excludeCreator')
                ->setParameter('excludeCreator', $excludeCreator);
        }

        $orX = $qb->expr()->orX();
        $index = 0;
        foreach (array_keys($normalizedTerms) as $term) {
            $param = 'kw' . $index++;
            $orX->add($qb->expr()->like('p.keywords', ':' . $param));
            $qb->setParameter($param, '%' . str_replace(' ', '%', $term) . '%');
        }

        $qb->andWhere($orX);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return PPBase[]
     */
    public function findLatestPublished(int $limit = 50): array
    {
        return $this->findLatestPublishedWindow($limit);
    }

    /**
     * @return PPBase[]
     */
    public function findLatestPublishedWindow(int $limit = 50, int $offset = 0, ?User $excludeCreator = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :notDeleted')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->orderBy('p.createdAt', 'DESC');

        if ($excludeCreator instanceof User) {
            $qb->andWhere('p.creator != :excludeCreator')
               ->setParameter('excludeCreator', $excludeCreator);
        }

        $offset = max(0, $offset);
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $qb
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PPBase[]
     */
    public function findLatestByCreator(User $creator, int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.creator = :creator')
            ->andWhere('p.isDeleted = :notDeleted')
            ->setParameter('creator', $creator)
            ->setParameter('notDeleted', false)
            ->addSelect('COALESCE(p.updatedAt, p.createdAt) AS HIDDEN activityAt')
            ->orderBy('activityAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PPBase[]
     */
    public function findLatestPublishedExcludingCreator(User $creator, int $limit = 6): array
    {
        return $this->findLatestPublishedWindow($limit, 0, $creator);
    }

    /**
     * @return PPBase[]
     */
    public function findPublishedByCategoriesForCreator(User $creator, array $categories, int $limit = 6): array
    {
        return $this->findPublishedByCategoriesWindow($categories, $limit, 0, $creator);
    }

    /**
     * @return PPBase[]
     */
    public function findPublishedNearLocation(
        float $lat,
        float $lng,
        float $radiusKm,
        int $limit = 24,
        ?User $excludeCreator = null
    ): array {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return [];
        }

        $radiusKm = max(1.0, min(200.0, $radiusKm));
        $bbox = $this->computeBoundingBox($lat, $lng, $radiusKm);

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p')
            ->join('p.places', 'pl')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :notDeleted')
            ->andWhere('pl.geoloc.latitude BETWEEN :minLat AND :maxLat')
            ->andWhere('pl.geoloc.longitude BETWEEN :minLng AND :maxLng')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->setParameter('minLat', $bbox['minLat'])
            ->setParameter('maxLat', $bbox['maxLat'])
            ->setParameter('minLng', $bbox['minLng'])
            ->setParameter('maxLng', $bbox['maxLng'])
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeCreator instanceof User) {
            $qb->andWhere('p.creator != :excludeCreator')
                ->setParameter('excludeCreator', $excludeCreator);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Preloads categories for the provided presentations to avoid per-card lazy-loading queries.
     *
     * @param int[] $ids
     */
    public function warmupCategoriesForIds(array $ids): void
    {
        $normalizedIds = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $normalizedIds[$id] = true;
            }
        }

        if ($normalizedIds === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->leftJoin('p.categories', 'c')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', array_keys($normalizedIds))
            ->getQuery()
            ->getResult();
    }

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

    /**
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    private function computeBoundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $earthRadiusKm = 6371.0;
        $latRad = deg2rad($lat);
        $latDelta = rad2deg($radiusKm / $earthRadiusKm);
        $lngDelta = rad2deg($radiusKm / ($earthRadiusKm * max(0.0001, cos($latRad))));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }

    /**
     * @param string[] $categories
     *
     * @return int[]
     */
    private function findPublishedIdsByCategoriesWindow(
        array $categories,
        int $limit,
        int $offset,
        ?User $excludeCreator
    ): array {
        $normalizedCategories = array_values(array_filter(array_unique(array_map(
            static fn (string $slug): string => trim($slug),
            $categories
        ))));
        if ($normalizedCategories === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
            SELECT p.id
            FROM ppbase p
            WHERE p.is_published = :published
              AND p.is_deleted = :notDeleted
              AND EXISTS (
                    SELECT 1
                    FROM category_ppbase cp
                    INNER JOIN category c ON c.id = cp.category_id
                    WHERE cp.ppbase_id = p.id
                      AND c.unique_name IN (:categories)
              )
        SQL;

        $params = [
            'published' => 1,
            'notDeleted' => 0,
            'categories' => $normalizedCategories,
            'limit' => max(1, $limit),
            'offset' => max(0, $offset),
        ];
        $types = [
            'published' => ParameterType::INTEGER,
            'notDeleted' => ParameterType::INTEGER,
            'categories' => ArrayParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];

        if ($excludeCreator instanceof User) {
            $creatorId = $excludeCreator->getId();
            if ($creatorId !== null) {
                $sql .= ' AND p.creator_id != :excludeCreatorId';
                $params['excludeCreatorId'] = $creatorId;
                $types['excludeCreatorId'] = ParameterType::INTEGER;
            }
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset';

        $rows = $conn->executeQuery($sql, $params, $types)->fetchFirstColumn();
        if ($rows === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $raw): int => (int) $raw,
            $rows
        ), static fn (int $id): bool => $id > 0));
    }
}
