<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Follow;
use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\User;
use App\Entity\PresentationEvent;
use App\Repository\PresentationEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MonitoringDashboardController extends AbstractController
{
    #[Route('/admin/monitoring', name: 'admin_monitoring', methods: ['GET'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        PresentationEventRepository $eventRepository,
    ): Response {
        [$start, $end, $rangeKey] = $this->resolveRange($request);

        $dateKeys = $this->buildDateKeys($start, $end);
        $dateLabels = array_map(
            static fn (string $key) => (new \DateTimeImmutable($key))->format('d/m'),
            $dateKeys
        );

        $userRepo = $em->getRepository(User::class);
        $ppRepo = $em->getRepository(PPBase::class);
        $commentRepo = $em->getRepository(Comment::class);
        $newsRepo = $em->getRepository(News::class);
        $likeRepo = $em->getRepository(Like::class);
        $followRepo = $em->getRepository(Follow::class);

        $usersTotal = $userRepo->count([]);
        $projectsTotal = (int) $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PPBase::class, 'p')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = 0)')
            ->getQuery()
            ->getSingleScalarResult();
        $commentsTotal = $commentRepo->count([]);
        $newsTotal = $newsRepo->count([]);

        $projectsDeleted = $this->countProjectsByFlag($em, 'isDeleted', true);
        $projectsPublished = $this->countProjectsByFlag($em, 'isPublished', true, true);
        $projectsUnpublished = $this->countProjectsByFlag($em, 'isPublished', false, true);

        $projectsAutomated = $this->countProjectsByIngestion($em, true);
        $projectsManual = $this->countProjectsByIngestion($em, false);

        $viewsTotal = (int) $em->createQueryBuilder()
            ->select('COALESCE(SUM(p.extra.viewsCount), 0)')
            ->from(PPBase::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        $sharesTotal = $eventRepository->countByType(PresentationEvent::TYPE_SHARE_OPEN, $start, $end)
            + $eventRepository->countByType(PresentationEvent::TYPE_SHARE_COPY, $start, $end)
            + $eventRepository->countByType(PresentationEvent::TYPE_SHARE_EXTERNAL, $start, $end);

        $newUsers = $this->countByRange($em, User::class, $start, $end);
        $newProjects = $this->countByRange($em, PPBase::class, $start, $end, [
            $this->getColumn($em, PPBase::class, 'isDeleted') . ' IS NULL OR ' . $this->getColumn($em, PPBase::class, 'isDeleted') . ' = 0',
        ]);
        $newComments = $this->countByRange($em, Comment::class, $start, $end);
        $newNews = $this->countByRange($em, News::class, $start, $end);
        $newLikes = $this->countByRange($em, Like::class, $start, $end);
        $newFollows = $this->countByRange($em, Follow::class, $start, $end);

        $dailyUsers = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, User::class, $start, $end)
        );
        $dailyProjectsManual = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, PPBase::class, $start, $end, [
                $this->getColumn($em, PPBase::class, 'isDeleted') . ' IS NULL OR ' . $this->getColumn($em, PPBase::class, 'isDeleted') . ' = 0',
                'ing_source_url IS NULL',
            ])
        );
        $dailyProjectsAutomated = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, PPBase::class, $start, $end, [
                $this->getColumn($em, PPBase::class, 'isDeleted') . ' IS NULL OR ' . $this->getColumn($em, PPBase::class, 'isDeleted') . ' = 0',
                'ing_source_url IS NOT NULL',
            ])
        );
        $dailyComments = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, Comment::class, $start, $end)
        );
        $dailyNews = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, News::class, $start, $end)
        );
        $dailyLikes = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, Like::class, $start, $end)
        );
        $dailyFollows = $this->seriesFromDayCounts(
            $dateKeys,
            $this->countByDay($em, Follow::class, $start, $end)
        );
        $dailyViews = $this->seriesFromDayCounts(
            $dateKeys,
            $eventRepository->countByTypeGroupedByDay(PresentationEvent::TYPE_VIEW, $start, $end)
        );
        $dailyShares = $this->seriesFromDayCounts(
            $dateKeys,
            $this->mergeDayCounts([
                $eventRepository->countByTypeGroupedByDay(PresentationEvent::TYPE_SHARE_OPEN, $start, $end),
                $eventRepository->countByTypeGroupedByDay(PresentationEvent::TYPE_SHARE_COPY, $start, $end),
                $eventRepository->countByTypeGroupedByDay(PresentationEvent::TYPE_SHARE_EXTERNAL, $start, $end),
            ])
        );

        $distinctVisitors = $eventRepository->countDistinctVisitors($start, $end);
        $returningVisitors = $eventRepository->countReturningVisitors($start, $end);

        $categoryRows = $em->createQueryBuilder()
            ->select('c.uniqueName AS uniqueName', 'c.label AS label', 'COUNT(p.id) AS total')
            ->from(Category::class, 'c')
            ->leftJoin('c.projectPresentation', 'p', 'WITH', '(p.isDeleted IS NULL OR p.isDeleted = 0)')
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $latestUsers = $userRepo->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $latestProjects = $ppRepo->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $latestComments = $commentRepo->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $latestNews = $newsRepo->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('admin/monitoring_dashboard.html.twig', [
            'rangeKey' => $rangeKey,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'dateLabels' => $dateLabels,
            'stats' => [
                'usersTotal' => $usersTotal,
                'projectsTotal' => $projectsTotal,
                'projectsPublished' => $projectsPublished,
                'projectsUnpublished' => $projectsUnpublished,
                'projectsDeleted' => $projectsDeleted,
                'projectsManual' => $projectsManual,
                'projectsAutomated' => $projectsAutomated,
                'commentsTotal' => $commentsTotal,
                'newsTotal' => $newsTotal,
                'viewsTotal' => $viewsTotal,
                'sharesTotal' => $sharesTotal,
                'newUsers' => $newUsers,
                'newProjects' => $newProjects,
                'newComments' => $newComments,
                'newNews' => $newNews,
                'newLikes' => $newLikes,
                'newFollows' => $newFollows,
                'distinctVisitors' => $distinctVisitors,
                'returningVisitors' => $returningVisitors,
            ],
            'series' => [
                'users' => $dailyUsers,
                'projectsManual' => $dailyProjectsManual,
                'projectsAutomated' => $dailyProjectsAutomated,
                'comments' => $dailyComments,
                'news' => $dailyNews,
                'likes' => $dailyLikes,
                'follows' => $dailyFollows,
                'views' => $dailyViews,
                'shares' => $dailyShares,
            ],
            'categories' => $categoryRows,
            'latestUsers' => $latestUsers,
            'latestProjects' => $latestProjects,
            'latestComments' => $latestComments,
            'latestNews' => $latestNews,
        ]);
    }

    /**
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable,2:string}
     */
    private function resolveRange(Request $request): array
    {
        $range = (string) $request->query->get('range', '30d');
        $startInput = $request->query->get('start');
        $endInput = $request->query->get('end');

        $end = new \DateTimeImmutable('today 23:59:59');
        $start = $end->modify('-29 days')->setTime(0, 0, 0);
        $rangeKey = $range;

        if ($range === '7d') {
            $start = $end->modify('-6 days')->setTime(0, 0, 0);
        } elseif ($range === '90d') {
            $start = $end->modify('-89 days')->setTime(0, 0, 0);
        } elseif ($range === '365d') {
            $start = $end->modify('-364 days')->setTime(0, 0, 0);
        } elseif ($range === 'custom' && is_string($startInput) && is_string($endInput)) {
            $customStart = \DateTimeImmutable::createFromFormat('Y-m-d', $startInput);
            $customEnd = \DateTimeImmutable::createFromFormat('Y-m-d', $endInput);
            if ($customStart && $customEnd) {
                $start = $customStart->setTime(0, 0, 0);
                $end = $customEnd->setTime(23, 59, 59);
                $rangeKey = 'custom';
            }
        }

        return [$start, $end, $rangeKey];
    }

    /**
     * @return string[]
     */
    private function buildDateKeys(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $keys = [];
        $period = new \DatePeriod(
            $start,
            new \DateInterval('P1D'),
            $end->modify('+1 day')
        );
        foreach ($period as $date) {
            $keys[] = $date->format('Y-m-d');
        }

        return $keys;
    }

    /**
     * @param array<string,int> $counts
     * @return int[]
     */
    private function seriesFromDayCounts(array $dateKeys, array $counts): array
    {
        $series = [];
        foreach ($dateKeys as $key) {
            $series[] = $counts[$key] ?? 0;
        }
        return $series;
    }

    /**
     * @param array<int,string> $filters
     */
    private function countByRange(
        EntityManagerInterface $em,
        string $entityClass,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $filters = [],
    ): int {
        $meta = $em->getClassMetadata($entityClass);
        $table = $meta->getTableName();
        $createdColumn = $meta->getColumnName('createdAt');
        $where = array_merge(["{$createdColumn} BETWEEN :start AND :end"], $filters);
        $sql = sprintf(
            'SELECT COUNT(*) AS total FROM %s WHERE %s',
            $table,
            implode(' AND ', array_map(static fn ($item) => '(' . $item . ')', $where))
        );
        $conn = $em->getConnection();
        $startParam = $start->format('Y-m-d H:i:s');
        $endParam = $end->format('Y-m-d H:i:s');
        return (int) $conn->executeQuery($sql, [
            'start' => $startParam,
            'end' => $endParam,
        ])->fetchOne();
    }

    /**
     * @param array<int,string> $filters
     *
     * @return array<string,int>
     */
    private function countByDay(
        EntityManagerInterface $em,
        string $entityClass,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $filters = [],
    ): array {
        $meta = $em->getClassMetadata($entityClass);
        $table = $meta->getTableName();
        $createdColumn = $meta->getColumnName('createdAt');
        $where = array_merge(["{$createdColumn} BETWEEN :start AND :end"], $filters);
        $sql = sprintf(
            'SELECT DATE(%s) AS day, COUNT(*) AS total FROM %s WHERE %s GROUP BY day',
            $createdColumn,
            $table,
            implode(' AND ', array_map(static fn ($item) => '(' . $item . ')', $where))
        );
        $conn = $em->getConnection();
        $startParam = $start->format('Y-m-d H:i:s');
        $endParam = $end->format('Y-m-d H:i:s');
        $rows = $conn->executeQuery($sql, [
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

    private function countProjectsByFlag(
        EntityManagerInterface $em,
        string $flag,
        bool $value,
        ?bool $excludeDeleted = null,
    ): int {
        $qb = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PPBase::class, 'p')
            ->andWhere(sprintf('p.%s = :value', $flag))
            ->setParameter('value', $value);

        if ($excludeDeleted !== null) {
            $qb->andWhere($excludeDeleted ? '(p.isDeleted IS NULL OR p.isDeleted = 0)' : 'p.isDeleted = 1');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countProjectsByIngestion(EntityManagerInterface $em, bool $automated): int
    {
        $qb = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PPBase::class, 'p')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = 0)');

        if ($automated) {
            $qb->andWhere('p.ingestion.sourceUrl IS NOT NULL');
        } else {
            $qb->andWhere('p.ingestion.sourceUrl IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<int, array<string,int>> $series
     *
     * @return array<string,int>
     */
    private function mergeDayCounts(array $series): array
    {
        $merged = [];
        foreach ($series as $counts) {
            foreach ($counts as $day => $count) {
                $merged[$day] = ($merged[$day] ?? 0) + $count;
            }
        }

        return $merged;
    }

    private function getColumn(EntityManagerInterface $em, string $entityClass, string $field): string
    {
        return $em->getClassMetadata($entityClass)->getColumnName($field);
    }
}
