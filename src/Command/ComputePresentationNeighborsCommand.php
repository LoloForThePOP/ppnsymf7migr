<?php

namespace App\Command;

use App\Service\AI\PresentationEmbeddingService;
use App\Service\Recommendation\PresentationNeighborRecomputeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/*
    Usage examples

    Recompute neighbors for all eligible embedded presentations (3P), K=30:
    bin/console app:compute-presentation-neighbors

    Recompute for one presentation only:
    bin/console app:compute-presentation-neighbors --presentation-id=123 --k=30

    Process only the latest 500 embedded presentations:
    bin/console app:compute-presentation-neighbors --limit=500 --k=30

    Include unpublished/deleted candidates:
    bin/console app:compute-presentation-neighbors --include-unpublished --include-deleted
 */

#[AsCommand(
    name: 'app:compute-presentation-neighbors',
    description: 'Recalcule les voisins de similarité des présentations à partir des embeddings existants (sans appel API embeddings).'
)]
class ComputePresentationNeighborsCommand extends Command
{
    public function __construct(
        private readonly PresentationNeighborRecomputeService $neighborRecomputeService,
        private readonly PresentationEmbeddingService $embeddingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('presentation-id', null, InputOption::VALUE_REQUIRED, 'ID de la présentation à traiter')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre de présentations cibles (0 = toutes)', 0)
            ->addOption('k', null, InputOption::VALUE_REQUIRED, 'Nombre de voisins à conserver', 30)
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Modèle d’embedding à utiliser (défaut: OPENAI_EMBEDDING_MODEL).')
            ->addOption('dims', null, InputOption::VALUE_REQUIRED, 'Dimensions des embeddings à utiliser (0 = OPENAI_EMBEDDING_DIMENSIONS).', 0)
            ->addOption('include-unpublished', null, InputOption::VALUE_NONE, 'Inclure les présentations non publiées')
            ->addOption('include-deleted', null, InputOption::VALUE_NONE, 'Inclure les présentations supprimées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $presentationIdOption = $input->getOption('presentation-id');
        $limit = max(0, (int) $input->getOption('limit'));
        $k = max(1, (int) $input->getOption('k'));
        $includeUnpublished = (bool) $input->getOption('include-unpublished');
        $includeDeleted = (bool) $input->getOption('include-deleted');

        $model = trim((string) ($input->getOption('model') ?? ''));
        if ($model === '') {
            $model = $this->embeddingService->getModel();
        }

        $dims = (int) $input->getOption('dims');
        if ($dims <= 0) {
            $dims = max(1, $this->embeddingService->getDimensions());
        }

        if ($presentationIdOption !== null) {
            $presentationId = (int) $presentationIdOption;
            if ($presentationId <= 0) {
                $io->error('L’option --presentation-id doit être un entier positif.');
                return Command::FAILURE;
            }
            $presentationIds = [$presentationId];
        } else {
            $presentationIds = $this->neighborRecomputeService->listPresentationIds(
                $model,
                $dims,
                $includeUnpublished,
                $includeDeleted,
                $limit
            );
        }

        if ($presentationIds === []) {
            $io->warning('Aucune présentation cible avec embeddings pour ce modèle/dims.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            'Recompute voisins: cibles=%d, model=%s, dims=%d, k=%d',
            count($presentationIds),
            $model,
            $dims,
            $k
        ));

        $stats = $this->neighborRecomputeService->recomputeForPresentationIds(
            $presentationIds,
            $model,
            $dims,
            $k,
            $includeUnpublished,
            $includeDeleted,
            $io
        );

        $io->success(sprintf(
            'Voisins recalculés: %d/%d (sans vecteur: %d, pool candidats: %d).',
            $stats['updated'],
            count($presentationIds),
            $stats['skippedMissingVector'],
            $stats['candidates']
        ));

        return Command::SUCCESS;
    }
}
