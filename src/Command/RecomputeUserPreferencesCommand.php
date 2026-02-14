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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d’utilisateurs traités', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getOption('user-id');
        $limit = max(1, (int) $input->getOption('limit'));

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
