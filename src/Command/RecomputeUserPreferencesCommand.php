<?php

namespace App\Command;

use App\Entity\User;
use App\Service\Recommendation\UserPreferenceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recompute-user-preferences',
    description: 'Recalcule le cache user_preferences à partir des interactions réelles.'
)]
final class RecomputeUserPreferencesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPreferenceUpdater $userPreferenceUpdater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Recalculer un seul utilisateur (id)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d’utilisateurs traités', 200)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Recalculer tous les utilisateurs (mode batch).')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille de lot en mode --all', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getOption('user-id');
        $limit = max(1, (int) $input->getOption('limit'));
        $all = (bool) $input->getOption('all');
        $batchSize = max(50, (int) $input->getOption('batch-size'));

        if ($all && $userId !== null && $userId !== '') {
            $io->error('Les options --all et --user-id sont incompatibles.');

            return Command::INVALID;
        }

        if ($all) {
            $processed = $this->recomputeAllUsers($batchSize, $io);
            $io->success(sprintf('user_preferences recalculé(s) (mode --all): %d', $processed));

            return Command::SUCCESS;
        }

        $users = $this->loadUsers($userId, $limit);
        if ($users === []) {
            $io->warning('Aucun utilisateur à traiter.');

            return Command::SUCCESS;
        }

        $processed = 0;
        foreach ($users as $user) {
            $this->userPreferenceUpdater->recomputeForUser($user, false);
            $processed++;

            if (($processed % 50) === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('user_preferences recalculé(s): %d', $processed));

        return Command::SUCCESS;
    }

    private function recomputeAllUsers(int $batchSize, SymfonyStyle $io): int
    {
        $repo = $this->entityManager->getRepository(User::class);
        $processed = 0;
        $lastId = 0;

        while (true) {
            $users = $repo->createQueryBuilder('u')
                ->andWhere('u.id > :lastId')
                ->setParameter('lastId', $lastId)
                ->orderBy('u.id', 'ASC')
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if ($users === []) {
                break;
            }

            foreach ($users as $user) {
                if (!$user instanceof User) {
                    continue;
                }

                $this->userPreferenceUpdater->recomputeForUser($user, false);
                $processed++;
                $lastId = max($lastId, (int) $user->getId());
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            if (($processed % $batchSize) === 0) {
                $io->writeln(sprintf('Progression --all: %d utilisateurs traités...', $processed));
            }
        }

        return $processed;
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
}
