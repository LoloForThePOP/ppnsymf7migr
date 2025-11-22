<?php

namespace App\Command;

use App\Service\ScraperIngestionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:collect-projects',
    description: 'Collecte des projets via le prompt de scraping et normalise le JSON'
)]
class CollectProjectsCommand extends Command
{
    public function __construct(
        private readonly ScraperIngestionService $scraperIngestionService
    ) {
        parent::__construct();
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
            $preview = array_slice($result['items'], 0, 5);
            $io->section('Aperçu (5 premiers)');
            $io->writeln(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $io->note('Cette commande normalise uniquement les données. La persistance en base (PPBase, slides, places...) reste à implémenter.');

        return Command::SUCCESS;
    }
}
