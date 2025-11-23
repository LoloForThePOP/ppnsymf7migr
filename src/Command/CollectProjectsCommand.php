<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ScraperIngestionService;
use App\Service\ScraperPersistenceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:collect-projects',
    description: 'Collecte des projets via le prompt de scraping et normalise le JSON'
)]
class CollectProjectsCommand extends Command
{
    public function __construct(
        private readonly ScraperIngestionService $scraperIngestionService,
        private readonly ScraperPersistenceService $scraperPersistenceService,
        private readonly UserRepository $userRepository,
        private readonly ?int $defaultCreatorId = null,
        private readonly ?string $defaultCreatorUsername = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('persist', null, InputOption::VALUE_NONE, 'Persister les projets en base')
            ->addOption('creator-id', null, InputOption::VALUE_REQUIRED, 'ID de l’utilisateur créateur (prioritaire si fourni)')
            ->addOption('creator-username', null, InputOption::VALUE_REQUIRED, 'Username de l’utilisateur créateur (utilisé si id absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->scraperIngestionService->fetchAndNormalize();
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec de la collecte: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf('Projets valides: %d | erreurs: %d', count($result['items']), count($result['errors'])));

        if ($io->isVerbose() && $result['errors']) {
            $io->section('Erreurs');
            foreach ($result['errors'] as $err) {
                $io->writeln('- ' . $err);
            }
        }

        if ($result['items']) {
            $preview = array_slice($result['items'], 0, 19);
            $io->section('Aperçu (5 premiers)');
            $io->writeln(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if ($input->getOption('persist')) {
            $creator = $this->resolveCreator(
                $input->getOption('creator-id'),
                $input->getOption('creator-username')
            );

            if (!$creator) {
                $io->error('Créateur introuvable. Fournissez --creator-id/--creator-username ou configurez app.scraper.creator_id/creator_username.');
                return Command::INVALID;
            }

            $persistResult = $this->scraperPersistenceService->persist($result['items'], $creator);
            $io->success(sprintf(
                'Persistés: %d | ignorés (doublons): %d | erreurs: %d',
                $persistResult['created'],
                $persistResult['skipped'],
                count($persistResult['errors'])
            ));

            if ($persistResult['errors']) {
                $io->section('Erreurs de persistance');
                foreach ($persistResult['errors'] as $err) {
                    $io->writeln('- ' . $err);
                }
            }
        } else {
            $io->note('Mode aperçu : utilisez --persist --creator-id=<id> pour enregistrer (slides/places non gérés dans cette passe).');
        }

        return Command::SUCCESS;
    }

    private function resolveCreator(?string $idOption, ?string $usernameOption): ?\App\Entity\User
    {
        // Priority: CLI id > CLI username > default id > default username
        if ($idOption) {
            return $this->userRepository->find($idOption);
        }

        if ($usernameOption) {
            return $this->userRepository->findOneBy(['username' => $usernameOption]);
        }

        if ($this->defaultCreatorId) {
            return $this->userRepository->find($this->defaultCreatorId);
        }

        if ($this->defaultCreatorUsername) {
            return $this->userRepository->findOneBy(['username' => $this->defaultCreatorUsername]);
        }

        return null;
    }
}
