<?php

namespace App\Command;

use App\Entity\Bookmark;
use App\Entity\Category;
use App\Entity\Follow;
use App\Entity\Like;
use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\PPBaseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:dev:seed-recommendation-qa',
    description: 'Create QA users and synthetic recommendation interactions (follows/likes/bookmarks).'
)]
final class SeedRecommendationQaCommand extends Command
{
    private const QA_EMAIL_TEMPLATE = 'qa-rec-%02d@example.test';
    private const QA_USERNAME_TEMPLATE = 'qa_rec_%02d';
    private const MAX_USERS = 40;
    private const MAX_TARGET = 220;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly PPBaseRepository $ppBaseRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('users', null, InputOption::VALUE_REQUIRED, 'How many QA users to create/update.', '6')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Approximate project interactions budget per QA user.', '24')
            ->addOption('min-projects-per-category', null, InputOption::VALUE_REQUIRED, 'Minimum published projects required for a category bucket.', '15')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password used for all QA users.', 'qa-rec-password')
            ->addOption('reset-interactions', null, InputOption::VALUE_NONE, 'Delete existing follow/like/bookmark rows for QA users before reseeding.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow running in prod environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->kernel->getEnvironment() === 'prod' && !$input->getOption('force')) {
            $io->error('This command is blocked in prod. Use --force only if you explicitly want it.');

            return Command::FAILURE;
        }

        $usersCount = $this->boundedInt((string) $input->getOption('users'), 1, self::MAX_USERS, 6);
        $targetPerUser = $this->boundedInt((string) $input->getOption('target'), 6, self::MAX_TARGET, 24);
        $minProjectsPerCategory = $this->boundedInt((string) $input->getOption('min-projects-per-category'), 3, 500, 15);
        $plainPassword = trim((string) $input->getOption('password'));
        if ($plainPassword === '') {
            $io->error('Password cannot be empty.');

            return Command::INVALID;
        }

        $publishedPool = $this->loadPublishedPool($usersCount, $targetPerUser);
        if ($publishedPool === []) {
            $io->error('No published projects found. Cannot seed recommendation QA interactions.');

            return Command::FAILURE;
        }

        $io->section('Preparing QA users');
        $qaUsers = $this->createOrUpdateQaUsers($usersCount, $plainPassword);
        $this->em->flush();

        if ($input->getOption('reset-interactions')) {
            $io->text('Resetting existing QA interactions (follow/like/bookmark)...');
            $this->deleteExistingInteractionsForUsers($qaUsers);
            $this->em->flush();
        }

        $followMap = $this->loadInteractionMap(Follow::class, 'f', $qaUsers);
        $likeMap = $this->loadInteractionMap(Like::class, 'l', $qaUsers);
        $bookmarkMap = $this->loadInteractionMap(Bookmark::class, 'b', $qaUsers);

        $categoryBuckets = $this->loadCategoryBuckets($usersCount, $targetPerUser, $minProjectsPerCategory);

        $io->section('Seeding synthetic interactions');
        $results = $this->seedInteractions(
            $qaUsers,
            $categoryBuckets,
            $publishedPool,
            $targetPerUser,
            $followMap,
            $likeMap,
            $bookmarkMap,
        );
        $this->em->flush();

        $io->success(sprintf(
            'QA seeding completed. Users: %d | follows+: %d | likes+: %d | bookmarks+: %d',
            count($qaUsers),
            $results['followsCreated'],
            $results['likesCreated'],
            $results['bookmarksCreated'],
        ));

        $io->table(
            ['QA user', 'Email', 'Password'],
            array_map(
                static fn (User $user): array => [
                    (string) $user->getUsername(),
                    (string) $user->getEmail(),
                    $plainPassword,
                ],
                $qaUsers
            )
        );

        if ($results['rows'] !== []) {
            $io->table(
                ['QA user', 'Primary category', 'Secondary category', 'follows+', 'likes+', 'bookmarks+'],
                $results['rows']
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @return PPBase[]
     */
    private function loadPublishedPool(int $usersCount, int $targetPerUser): array
    {
        $poolLimit = max(400, $usersCount * $targetPerUser * 8);
        $poolLimit = min(5000, $poolLimit);

        $pool = $this->ppBaseRepository->findLatestPublished($poolLimit);
        shuffle($pool);

        return $pool;
    }

    /**
     * @return User[]
     */
    private function createOrUpdateQaUsers(int $usersCount, string $plainPassword): array
    {
        $users = [];
        for ($index = 1; $index <= $usersCount; $index++) {
            $email = sprintf(self::QA_EMAIL_TEMPLATE, $index);
            $username = sprintf(self::QA_USERNAME_TEMPLATE, $index);

            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $user = (new User())
                    ->setEmail($email)
                    ->setUsername($username);
            }

            $user
                ->setUsername($username)
                ->setIsActive(true)
                ->setIsVerified(true)
                ->setRoles(['ROLE_USER'])
                ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->em->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @param User[] $users
     */
    private function deleteExistingInteractionsForUsers(array $users): void
    {
        $this->em->createQueryBuilder()
            ->delete(Follow::class, 'f')
            ->where('f.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->execute();

        $this->em->createQueryBuilder()
            ->delete(Like::class, 'l')
            ->where('l.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->execute();

        $this->em->createQueryBuilder()
            ->delete(Bookmark::class, 'b')
            ->where('b.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->execute();
    }

    /**
     * @param User[] $users
     *
     * @return array<int, array<int, true>>
     */
    private function loadInteractionMap(string $entityClass, string $alias, array $users): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select(sprintf('IDENTITY(%s.user) AS userId', $alias), sprintf('IDENTITY(%s.projectPresentation) AS projectId', $alias))
            ->from($entityClass, $alias)
            ->where(sprintf('%s.user IN (:users)', $alias))
            ->setParameter('users', $users)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['userId'] ?? 0);
            $projectId = (int) ($row['projectId'] ?? 0);
            if ($userId <= 0 || $projectId <= 0) {
                continue;
            }
            $map[$userId][$projectId] = true;
        }

        return $map;
    }

    /**
     * @return array<int, array{slug:string, projects:PPBase[]}>
     */
    private function loadCategoryBuckets(int $usersCount, int $targetPerUser, int $minProjectsPerCategory): array
    {
        $categoryLimit = min(30, max(6, $usersCount * 3));
        $bucketProjectLimit = min(1200, max(120, $targetPerUser * 16));
        $minimumBucketSize = max(6, (int) ceil($targetPerUser * 0.6));

        $categoryQb = $this->em->createQueryBuilder()
            ->select('c', 'COUNT(p.id) AS HIDDEN projectsCount')
            ->from(Category::class, 'c')
            ->innerJoin('c.projectPresentation', 'p')
            ->where('p.isPublished = :published')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = :notDeleted)')
            ->groupBy('c.id')
            ->having('COUNT(p.id) >= :minProjects')
            ->orderBy('projectsCount', 'DESC')
            ->setMaxResults($categoryLimit)
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->setParameter('minProjects', $minProjectsPerCategory);

        /** @var Category[] $categories */
        $categories = $categoryQb->getQuery()->getResult();

        $buckets = [];
        foreach ($categories as $category) {
            $slug = trim((string) $category->getUniqueName());
            if ($slug === '') {
                continue;
            }

            $projectQb = $this->em->createQueryBuilder()
                ->select('p')
                ->from(PPBase::class, 'p')
                ->innerJoin('p.categories', 'cat')
                ->where('cat = :category')
                ->andWhere('p.isPublished = :published')
                ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = :notDeleted)')
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults($bucketProjectLimit)
                ->setParameter('category', $category)
                ->setParameter('published', true)
                ->setParameter('notDeleted', false);

            /** @var PPBase[] $projects */
            $projects = $projectQb->getQuery()->getResult();
            if (count($projects) < $minimumBucketSize) {
                continue;
            }

            shuffle($projects);
            $buckets[] = [
                'slug' => $slug,
                'projects' => $projects,
            ];
        }

        return $buckets;
    }

    /**
     * @param User[] $users
     * @param array<int, array{slug:string, projects:PPBase[]}> $categoryBuckets
     * @param PPBase[] $publishedPool
     * @param array<int, array<int, true>> $followMap
     * @param array<int, array<int, true>> $likeMap
     * @param array<int, array<int, true>> $bookmarkMap
     *
     * @return array{
     *   followsCreated:int,
     *   likesCreated:int,
     *   bookmarksCreated:int,
     *   rows: array<int, array<int, string|int>>
     * }
     */
    private function seedInteractions(
        array $users,
        array $categoryBuckets,
        array $publishedPool,
        int $targetPerUser,
        array &$followMap,
        array &$likeMap,
        array &$bookmarkMap,
    ): array {
        $followsCreated = 0;
        $likesCreated = 0;
        $bookmarksCreated = 0;
        $rows = [];
        $poolCursor = 0;

        foreach ($users as $index => $user) {
            $userId = (int) $user->getId();
            if ($userId <= 0) {
                continue;
            }

            $primaryBucket = $categoryBuckets !== [] ? $categoryBuckets[$index % count($categoryBuckets)] : null;
            $secondaryBucket = null;
            if ($primaryBucket !== null && count($categoryBuckets) > 1) {
                $secondaryBucket = $categoryBuckets[($index + 1) % count($categoryBuckets)];
            }

            $seenProjectIds = [];
            $primaryTarget = (int) floor($targetPerUser * 0.65);
            $secondaryTarget = (int) floor($targetPerUser * 0.25);
            $exploreTarget = max(0, $targetPerUser - $primaryTarget - $secondaryTarget);

            $primaryProjects = $primaryBucket
                ? $this->takeProjects($primaryBucket['projects'], $primaryTarget, $seenProjectIds)
                : [];
            $secondaryProjects = $secondaryBucket
                ? $this->takeProjects($secondaryBucket['projects'], $secondaryTarget, $seenProjectIds)
                : [];
            $exploreProjects = $this->takeProjectsFromPool($publishedPool, $exploreTarget, $seenProjectIds, $poolCursor);

            if (count($primaryProjects) + count($secondaryProjects) + count($exploreProjects) < $targetPerUser) {
                $missing = $targetPerUser - (count($primaryProjects) + count($secondaryProjects) + count($exploreProjects));
                $extra = $this->takeProjectsFromPool($publishedPool, $missing, $seenProjectIds, $poolCursor);
                $exploreProjects = array_merge($exploreProjects, $extra);
            }

            $createdPerUser = [
                'follows' => 0,
                'likes' => 0,
                'bookmarks' => 0,
            ];

            $createdPerUser['follows'] += $this->applyInteractionsByCap($user, $primaryProjects, min(10, count($primaryProjects)), 'follow', $followMap);
            $createdPerUser['follows'] += $this->applyInteractionsByCap($user, $secondaryProjects, min(4, count($secondaryProjects)), 'follow', $followMap);

            $createdPerUser['likes'] += $this->applyInteractionsByCap($user, $primaryProjects, min(8, count($primaryProjects)), 'like', $likeMap);
            $createdPerUser['likes'] += $this->applyInteractionsByCap($user, $secondaryProjects, min(3, count($secondaryProjects)), 'like', $likeMap);
            $createdPerUser['likes'] += $this->applyInteractionsByCap($user, $exploreProjects, min(1, count($exploreProjects)), 'like', $likeMap);

            $createdPerUser['bookmarks'] += $this->applyInteractionsByCap($user, $primaryProjects, min(6, count($primaryProjects)), 'bookmark', $bookmarkMap);
            $createdPerUser['bookmarks'] += $this->applyInteractionsByCap($user, $secondaryProjects, min(2, count($secondaryProjects)), 'bookmark', $bookmarkMap);

            $followsCreated += $createdPerUser['follows'];
            $likesCreated += $createdPerUser['likes'];
            $bookmarksCreated += $createdPerUser['bookmarks'];

            $rows[] = [
                (string) $user->getUsername(),
                $primaryBucket['slug'] ?? '-',
                $secondaryBucket['slug'] ?? '-',
                $createdPerUser['follows'],
                $createdPerUser['likes'],
                $createdPerUser['bookmarks'],
            ];
        }

        return [
            'followsCreated' => $followsCreated,
            'likesCreated' => $likesCreated,
            'bookmarksCreated' => $bookmarksCreated,
            'rows' => $rows,
        ];
    }

    /**
     * @param PPBase[] $projects
     * @param array<int, true> $seenProjectIds
     *
     * @return PPBase[]
     */
    private function takeProjects(array $projects, int $limit, array &$seenProjectIds): array
    {
        if ($limit <= 0 || $projects === []) {
            return [];
        }

        $picked = [];
        foreach ($projects as $project) {
            $projectId = (int) $project->getId();
            if ($projectId <= 0 || isset($seenProjectIds[$projectId])) {
                continue;
            }

            $seenProjectIds[$projectId] = true;
            $picked[] = $project;

            if (count($picked) >= $limit) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param PPBase[] $pool
     * @param array<int, true> $seenProjectIds
     *
     * @return PPBase[]
     */
    private function takeProjectsFromPool(array $pool, int $limit, array &$seenProjectIds, int &$cursor): array
    {
        if ($limit <= 0 || $pool === []) {
            return [];
        }

        $picked = [];
        $poolSize = count($pool);
        $attempts = 0;
        $maxAttempts = $poolSize * 3;

        while (count($picked) < $limit && $attempts < $maxAttempts) {
            $index = $cursor % $poolSize;
            $cursor++;
            $attempts++;

            $project = $pool[$index];
            $projectId = (int) $project->getId();
            if ($projectId <= 0 || isset($seenProjectIds[$projectId])) {
                continue;
            }

            $seenProjectIds[$projectId] = true;
            $picked[] = $project;
        }

        return $picked;
    }

    /**
     * @param PPBase[] $projects
     * @param array<int, array<int, true>> $interactionMap
     */
    private function applyInteractionsByCap(
        User $user,
        array $projects,
        int $cap,
        string $interactionType,
        array &$interactionMap,
    ): int {
        if ($cap <= 0 || $projects === []) {
            return 0;
        }

        $created = 0;
        foreach ($projects as $project) {
            if ($created >= $cap) {
                break;
            }

            $created += $this->createInteraction($interactionType, $user, $project, $interactionMap) ? 1 : 0;
        }

        return $created;
    }

    /**
     * @param array<int, array<int, true>> $interactionMap
     */
    private function createInteraction(
        string $interactionType,
        User $user,
        PPBase $project,
        array &$interactionMap,
    ): bool {
        $userId = (int) $user->getId();
        $projectId = (int) $project->getId();
        if ($userId <= 0 || $projectId <= 0) {
            return false;
        }
        if (isset($interactionMap[$userId][$projectId])) {
            return false;
        }

        if ($interactionType === 'follow') {
            $entity = (new Follow())
                ->setUser($user)
                ->setProjectPresentation($project);
        } elseif ($interactionType === 'like') {
            $entity = (new Like())
                ->setUser($user)
                ->setProjectPresentation($project);
        } else {
            $entity = (new Bookmark())
                ->setUser($user)
                ->setProjectPresentation($project);
        }

        $this->em->persist($entity);
        $interactionMap[$userId][$projectId] = true;

        return true;
    }

    private function boundedInt(string $raw, int $min, int $max, int $default): int
    {
        if (!is_numeric($raw)) {
            return $default;
        }

        $value = (int) $raw;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
