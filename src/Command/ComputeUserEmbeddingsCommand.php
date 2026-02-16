<?php

namespace App\Command;

use App\Entity\Bookmark;
use App\Entity\Follow;
use App\Entity\Like;
use App\Entity\PresentationEmbedding;
use App\Entity\PresentationEvent;
use App\Entity\User;
use App\Entity\UserEmbedding;
use App\Entity\UserPreference;
use App\Repository\UserEmbeddingRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\AI\PresentationEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/*
    Usage examples

    Default (centroid mode):
    bin/console app:compute-user-embeddings

    Process one user:
    bin/console app:compute-user-embeddings --user-id=123

    Batch run with larger window and relaxed cooldown:
    bin/console app:compute-user-embeddings --limit=500 --cooldown-hours=1

    Force recompute in centroid mode:
    bin/console app:compute-user-embeddings --mode=centroid --force

    Compatibility text mode (uses user_preferences + embedding API):
    bin/console app:compute-user-embeddings --mode=text --limit=200
 */

#[AsCommand(
    name: 'app:compute-user-embeddings',
    description: 'Construit les embeddings utilisateurs (centroides de projets ou mode texte).'
)]
final class ComputeUserEmbeddingsCommand extends Command
{
    // Kept aligned with UserPreferenceUpdater to avoid diverging interaction semantics.
    private const MAX_INTERACTIONS_PER_SIGNAL = 600;
    private const MAX_PROJECTS_FOR_CENTROID = 500;
    private const VIEW_LOOKBACK_DAYS = 90;

    private const WEIGHT_LIKE = 3.0;
    private const WEIGHT_FOLLOW = 4.0;
    private const WEIGHT_BOOKMARK = 2.5;
    private const WEIGHT_VIEW = 0.9;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPreferenceRepository $userPreferenceRepository,
        private readonly UserEmbeddingRepository $userEmbeddingRepository,
        private readonly PresentationEmbeddingService $embeddingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode de calcul: centroid|text', 'centroid')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Calculer un seul utilisateur (id)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d’utilisateurs traités', 200)
            ->addOption('cooldown-hours', null, InputOption::VALUE_REQUIRED, 'Délai minimal entre recalculs (heures)', 6)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer le recalcul même si le profil n’a pas changé');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = strtolower(trim((string) $input->getOption('mode')));
        if (!in_array($mode, ['centroid', 'text'], true)) {
            $io->error('Mode invalide. Utiliser --mode=centroid ou --mode=text.');

            return Command::INVALID;
        }

        if ($mode === 'text' && !$this->embeddingService->isConfigured()) {
            $io->error('Le client OpenAI n’est pas configuré (OPENAI_API_KEY manquante).');

            return Command::FAILURE;
        }

        $limit = max(1, (int) $input->getOption('limit'));
        $userId = $input->getOption('user-id');
        $cooldownHours = max(0, (int) $input->getOption('cooldown-hours'));
        $force = (bool) $input->getOption('force');

        $cooldownThreshold = null;
        if ($cooldownHours > 0) {
            $cooldownThreshold = (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('PT%dH', $cooldownHours)));
        }

        $users = $this->loadUsers($userId, $limit);
        if ($users === []) {
            $io->warning('Aucun utilisateur à traiter.');

            return Command::SUCCESS;
        }

        $model = $this->embeddingService->getModel();
        $dims = max(1, $this->embeddingService->getDimensions());
        $processed = 0;
        $skippedNoSignal = 0;
        $skippedNoVector = 0;
        $skippedNoPreference = 0;

        foreach ($users as $user) {
            $existing = $this->userEmbeddingRepository->findOneBy([
                'user' => $user,
                'model' => $model,
            ]);

            if ($mode === 'centroid') {
                $projectScores = $this->collectWeightedProjectScores($user);
                if ($projectScores === []) {
                    $skippedNoSignal++;
                    continue;
                }

                $payload = $this->buildCentroidPayload($projectScores, $model, $dims);
                if ($payload === null) {
                    $skippedNoVector++;
                    continue;
                }

                if ($this->shouldSkipUpdate($existing, $payload['contentHash'], $force, $cooldownThreshold)) {
                    continue;
                }

                $this->persistEmbedding($existing, $user, $payload);
            } else {
                $preference = $this->userPreferenceRepository->findOneBy(['user' => $user]);
                if (!$preference instanceof UserPreference) {
                    $skippedNoPreference++;
                    continue;
                }

                $text = $this->buildPreferenceText($preference);
                if ($text === '') {
                    $skippedNoPreference++;
                    continue;
                }

                $contentHash = hash('sha256', $text, true);
                if ($this->shouldSkipUpdate($existing, $contentHash, $force, $cooldownThreshold)) {
                    continue;
                }

                $result = $this->embeddingService->buildForText($text, $contentHash);
                if ($result === null) {
                    $io->warning(sprintf('Embedding non généré pour user_id=%d', $user->getId() ?? 0));
                    continue;
                }

                $payload = [
                    'model' => $result->model,
                    'dimensions' => $result->dimensions,
                    'normalized' => $result->normalized,
                    'vectorBinary' => $result->vectorBinary,
                    'contentHash' => $result->contentHash,
                ];
                $this->persistEmbedding($existing, $user, $payload);
            }

            $processed++;

            if (($processed % 25) === 0) {
                $this->entityManager->flush();
            }
        }

        if ($processed > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'user_embeddings mis à jour (%s): %d | sans signaux: %d | sans vecteurs: %d | sans préférences: %d',
            $mode,
            $processed,
            $skippedNoSignal,
            $skippedNoVector,
            $skippedNoPreference
        ));

        return Command::SUCCESS;
    }

    /**
     * @return User[]
     */
    private function loadUsers(?string $userId, int $limit): array
    {
        $repo = $this->entityManager->getRepository(User::class);

        if ($userId !== null && $userId !== '') {
            $user = $repo->find((int) $userId);

            return $user instanceof User ? [$user] : [];
        }

        return $repo->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function buildPreferenceText(UserPreference $preference): string
    {
        $categories = array_slice(array_keys($preference->getFavCategories()), 0, 12);
        $keywords = array_slice(array_keys($preference->getFavKeywords()), 0, 40);

        if ($categories === [] && $keywords === []) {
            return '';
        }

        $sections = [];

        if ($categories !== []) {
            $sections[] = 'categories: ' . implode(', ', $categories);
        }

        if ($keywords !== []) {
            $sections[] = 'keywords: ' . implode(', ', $keywords);
        }

        return implode("\n", $sections);
    }

    /**
     * @return array<int,float>
     */
    private function collectWeightedProjectScores(User $user): array
    {
        $scores = [];

        $this->accumulateInteractionScores(Like::class, 'l', $user, self::WEIGHT_LIKE, $scores);
        $this->accumulateInteractionScores(Follow::class, 'f', $user, self::WEIGHT_FOLLOW, $scores);
        $this->accumulateInteractionScores(Bookmark::class, 'b', $user, self::WEIGHT_BOOKMARK, $scores);
        $this->accumulateViewScores($user, $scores);

        if ($scores === []) {
            return [];
        }

        foreach ($scores as $projectId => $score) {
            if ($projectId <= 0 || $score <= 0.0) {
                unset($scores[$projectId]);
            }
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores, SORT_NUMERIC);

        return array_slice($scores, 0, self::MAX_PROJECTS_FOR_CENTROID, true);
    }

    /**
     * @param array<int,float> $scores
     */
    private function accumulateInteractionScores(
        string $entityClass,
        string $alias,
        User $user,
        float $weight,
        array &$scores
    ): void {
        if ($weight <= 0.0) {
            return;
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select(sprintf('IDENTITY(%s.projectPresentation) AS projectId', $alias))
            ->from($entityClass, $alias)
            ->innerJoin(sprintf('%s.projectPresentation', $alias), 'p')
            ->where(sprintf('%s.user = :user', $alias))
            ->andWhere('p.isPublished = true')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = false)')
            ->orderBy(sprintf('%s.createdAt', $alias), 'DESC')
            ->setMaxResults(self::MAX_INTERACTIONS_PER_SIGNAL)
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $projectId = (int) ($row['projectId'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $scores[$projectId] = ($scores[$projectId] ?? 0.0) + $weight;
        }
    }

    /**
     * @param array<int,float> $scores
     */
    private function accumulateViewScores(User $user, array &$scores): void
    {
        $since = (new \DateTimeImmutable())->sub(
            new \DateInterval(sprintf('P%dD', self::VIEW_LOOKBACK_DAYS))
        );

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.projectPresentation) AS projectId', 'COUNT(e.id) AS viewCount')
            ->from(PresentationEvent::class, 'e')
            ->where('e.user = :user')
            ->andWhere('e.type = :type')
            ->andWhere('e.createdAt >= :since')
            ->groupBy('e.projectPresentation')
            ->orderBy('viewCount', 'DESC')
            ->setMaxResults(self::MAX_INTERACTIONS_PER_SIGNAL)
            ->setParameter('user', $user)
            ->setParameter('type', PresentationEvent::TYPE_VIEW)
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $projectId = (int) ($row['projectId'] ?? 0);
            $viewCount = max(0, (int) ($row['viewCount'] ?? 0));
            if ($projectId <= 0 || $viewCount <= 0) {
                continue;
            }

            $weight = self::WEIGHT_VIEW * min(4.0, sqrt((float) $viewCount));
            if ($weight <= 0.0) {
                continue;
            }

            $scores[$projectId] = ($scores[$projectId] ?? 0.0) + $weight;
        }
    }

    /**
     * @param array<int,float> $projectScores
     *
     * @return array{
     *   model:string,
     *   dimensions:int,
     *   normalized:bool,
     *   vectorBinary:string,
     *   contentHash:string
     * }|null
     */
    private function buildCentroidPayload(array $projectScores, string $model, int $dims): ?array
    {
        if ($projectScores === []) {
            return null;
        }

        $embeddings = $this->entityManager->createQueryBuilder()
            ->select('e', 'p')
            ->from(PresentationEmbedding::class, 'e')
            ->join('e.presentation', 'p')
            ->where('e.model = :model')
            ->andWhere('e.dims = :dims')
            ->andWhere('p.id IN (:ids)')
            ->andWhere('p.isPublished = true')
            ->andWhere('(p.isDeleted IS NULL OR p.isDeleted = false)')
            ->setParameter('model', $model)
            ->setParameter('dims', $dims)
            ->setParameter('ids', array_keys($projectScores))
            ->getQuery()
            ->getResult();

        if ($embeddings === []) {
            return null;
        }

        $weightedSum = [];
        $totalWeight = 0.0;
        $usedScores = [];

        foreach ($embeddings as $embedding) {
            if (!$embedding instanceof PresentationEmbedding) {
                continue;
            }

            $presentationId = $embedding->getPresentation()->getId();
            if ($presentationId === null) {
                continue;
            }

            $weight = (float) ($projectScores[$presentationId] ?? 0.0);
            if ($weight <= 0.0) {
                continue;
            }

            $vector = $this->unpackVector($embedding->getVectorBinary());
            if ($vector === []) {
                continue;
            }

            if (!$embedding->isNormalized()) {
                $vector = $this->normalizeVector($vector)[0];
            }

            if ($weightedSum === []) {
                $weightedSum = array_fill(0, count($vector), 0.0);
            }

            if (count($vector) !== count($weightedSum)) {
                continue;
            }

            foreach ($vector as $index => $value) {
                $weightedSum[$index] += $value * $weight;
            }

            $totalWeight += $weight;
            $usedScores[$presentationId] = $weight;
        }

        if ($weightedSum === [] || $totalWeight <= 0.0 || $usedScores === []) {
            return null;
        }

        foreach ($weightedSum as $index => $value) {
            $weightedSum[$index] = $value / $totalWeight;
        }

        [$centroid, $normalized] = $this->normalizeVector($weightedSum);
        $centroidDimensions = count($centroid);

        ksort($usedScores);
        $signature = [];
        foreach ($usedScores as $projectId => $weight) {
            $signature[] = sprintf('%d:%0.6F', (int) $projectId, (float) $weight);
        }

        $contentHash = hash('sha256', sprintf(
            'centroid-v1|%s|%d|%s',
            $model,
            $centroidDimensions,
            implode('|', $signature)
        ), true);

        return [
            'model' => $model,
            'dimensions' => $centroidDimensions,
            'normalized' => $normalized,
            'vectorBinary' => $this->packVector($centroid),
            'contentHash' => $contentHash,
        ];
    }

    /**
     * @param array{
     *   model:string,
     *   dimensions:int,
     *   normalized:bool,
     *   vectorBinary:string,
     *   contentHash:string
     * } $payload
     */
    private function persistEmbedding(?UserEmbedding $existing, User $user, array $payload): void
    {
        if (!$existing instanceof UserEmbedding) {
            $existing = new UserEmbedding($user, $payload['model']);
            $this->entityManager->persist($existing);
        }

        $existing
            ->setDims((int) $payload['dimensions'])
            ->setNormalized((bool) $payload['normalized'])
            ->setVectorBinary((string) $payload['vectorBinary'])
            ->setContentHash((string) $payload['contentHash']);
    }

    private function shouldSkipUpdate(
        ?UserEmbedding $existing,
        string $contentHash,
        bool $force,
        ?\DateTimeImmutable $cooldownThreshold
    ): bool {
        if (!$existing instanceof UserEmbedding || $force) {
            return false;
        }

        if (hash_equals($existing->getContentHash(), $contentHash)) {
            return true;
        }

        return $cooldownThreshold !== null && $existing->getUpdatedAt() > $cooldownThreshold;
    }

    /**
     * @return float[]
     */
    private function unpackVector(string $binary): array
    {
        $data = unpack('g*', $binary);
        if ($data === false) {
            return [];
        }

        return array_map(static fn ($value): float => (float) $value, array_values($data));
    }

    /**
     * @param float[] $vector
     *
     * @return array{0: float[], 1: bool}
     */
    private function normalizeVector(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $value = (float) $value;
            $sum += $value * $value;
        }

        $norm = sqrt($sum);
        if ($norm <= 0.0) {
            return [array_map(static fn ($value): float => (float) $value, $vector), false];
        }

        $normalized = array_map(
            static fn ($value): float => (float) $value / $norm,
            $vector
        );

        return [$normalized, true];
    }

    /**
     * @param float[] $vector
     */
    private function packVector(array $vector): string
    {
        $binary = '';
        foreach ($vector as $value) {
            $binary .= pack('g', (float) $value);
        }

        return $binary;
    }
}
