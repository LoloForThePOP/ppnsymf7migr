<?php

namespace App\Command;

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

#[AsCommand(
    name: 'app:compute-user-embeddings',
    description: 'Construit les embeddings utilisateurs à partir du cache user_preferences.'
)]
final class ComputeUserEmbeddingsCommand extends Command
{
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
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Calculer un seul utilisateur (id)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d’utilisateurs traités', 200)
            ->addOption('cooldown-hours', null, InputOption::VALUE_REQUIRED, 'Délai minimal entre recalculs (heures)', 6)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer le recalcul même si le profil n’a pas changé');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->embeddingService->isConfigured()) {
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

        $preferences = $this->loadPreferences($userId, $limit);
        if ($preferences === []) {
            $io->warning('Aucun profil utilisateur exploitable.');

            return Command::SUCCESS;
        }

        $model = $this->embeddingService->getModel();
        $processed = 0;

        foreach ($preferences as $preference) {
            $text = $this->buildPreferenceText($preference);
            if ($text === '') {
                continue;
            }

            $contentHash = hash('sha256', $text, true);
            $existing = $this->userEmbeddingRepository->findOneBy([
                'user' => $preference->getUser(),
                'model' => $model,
            ]);

            if ($existing instanceof UserEmbedding && !$force) {
                if (hash_equals($existing->getContentHash(), $contentHash)) {
                    continue;
                }

                if ($cooldownThreshold !== null && $existing->getUpdatedAt() > $cooldownThreshold) {
                    continue;
                }
            }

            $result = $this->embeddingService->buildForText($text, $contentHash);
            if ($result === null) {
                $io->warning(sprintf('Embedding non généré pour user_id=%d', $preference->getUser()->getId() ?? 0));
                continue;
            }

            if (!$existing instanceof UserEmbedding) {
                $existing = new UserEmbedding($preference->getUser(), $result->model);
                $this->entityManager->persist($existing);
            }

            $existing
                ->setDims($result->dimensions)
                ->setNormalized($result->normalized)
                ->setVectorBinary($result->vectorBinary)
                ->setContentHash($result->contentHash);

            $processed++;

            if (($processed % 25) === 0) {
                $this->entityManager->flush();
            }
        }

        if ($processed > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('user_embeddings mis à jour: %d', $processed));

        return Command::SUCCESS;
    }

    /**
     * @return UserPreference[]
     */
    private function loadPreferences(?string $userId, int $limit): array
    {
        $qb = $this->userPreferenceRepository->createQueryBuilder('up')
            ->join('up.user', 'u')
            ->addSelect('u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        if ($userId !== null && $userId !== '') {
            $qb->andWhere('u.id = :userId')
                ->setParameter('userId', (int) $userId)
                ->setMaxResults(1);
        }

        return $qb->getQuery()->getResult();
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
}
