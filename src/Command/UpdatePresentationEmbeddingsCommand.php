<?php

namespace App\Command;

use App\Entity\PPBase;
use App\Entity\PresentationEmbedding;
use App\Entity\PresentationNeighbor;
use App\Service\AI\PresentationEmbeddingService;
use App\Service\AI\PresentationEmbeddingTextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/*
    Usage examples

    Basic run (default 50 items, cooldown 6h, K=10):
    bin/console app:compute-presentation-embeddings

    Force recompute for one presentation:
    bin/console app:compute-presentation-embeddings --presentation-id=123 --force

    Recompute embeddings only (no neighbors):
    bin/console app:compute-presentation-embeddings --no-neighbors
    
    Include unpublished items:
    bin/console app:compute-presentation-embeddings --include-unpublished
 */

#[AsCommand(
    name: 'app:compute-presentation-embeddings',
    description: 'Génère les embeddings des présentations et recalcule les voisins.'
)]
class UpdatePresentationEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PresentationEmbeddingService $embeddingService,
        private readonly PresentationEmbeddingTextBuilder $textBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('presentation-id', null, InputOption::VALUE_REQUIRED, 'ID de la présentation à traiter')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre de présentations', 50)
            ->addOption('cooldown-hours', null, InputOption::VALUE_REQUIRED, 'Délai minimal entre recalculs (en heures)', 6)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer le recalcul même si le hash n’a pas changé')
            ->addOption('no-neighbors', null, InputOption::VALUE_NONE, 'Ne pas recalculer les voisins')
            ->addOption('k', null, InputOption::VALUE_REQUIRED, 'Nombre de voisins à conserver', 10)
            ->addOption('include-unpublished', null, InputOption::VALUE_NONE, 'Inclure les présentations non publiées')
            ->addOption('include-deleted', null, InputOption::VALUE_NONE, 'Inclure les présentations supprimées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->embeddingService->isConfigured()) {
            $io->error('Le client OpenAI n’est pas configuré (OPENAI_API_KEY manquante).');
            return Command::FAILURE;
        }

        $presentationId = $input->getOption('presentation-id');
        $limit = max(1, (int) $input->getOption('limit'));
        $cooldownHours = max(0, (int) $input->getOption('cooldown-hours'));
        $force = (bool) $input->getOption('force');
        $computeNeighbors = !$input->getOption('no-neighbors');
        $k = max(1, (int) $input->getOption('k'));
        $includeUnpublished = (bool) $input->getOption('include-unpublished');
        $includeDeleted = (bool) $input->getOption('include-deleted');

        $model = $this->embeddingService->getModel();
        $dims = $this->embeddingService->getDimensions();

        $presentations = $this->loadPresentations($presentationId, $includeUnpublished, $includeDeleted, $limit);
        if ($presentations === []) {
            $io->warning('Aucune présentation à traiter.');
            return Command::SUCCESS;
        }

        $cooldownThreshold = null;
        if ($cooldownHours > 0) {
            $cooldownThreshold = (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('PT%dH', $cooldownHours)));
        }

        $embeddingRepo = $this->em->getRepository(PresentationEmbedding::class);
        $updatedPresentationIds = [];
        $processed = 0;

        foreach ($presentations as $presentation) {
            $text = $this->textBuilder->buildText($presentation);
            if ($text === '') {
                $io->writeln(sprintf('Presentation %d: texte vide, ignorée.', $presentation->getId()));
                continue;
            }

            $hash = $this->textBuilder->hashText($text, true);
            $existing = $embeddingRepo->findOneBy([
                'presentation' => $presentation,
                'model' => $model,
            ]);

            if ($existing && !$force) {
                if (hash_equals($existing->getContentHash(), $hash)) {
                    $io->writeln(sprintf('Presentation %d: hash inchangé.', $presentation->getId()));
                    continue;
                }

                if ($cooldownThreshold && $existing->getUpdatedAt() > $cooldownThreshold) {
                    $io->writeln(sprintf('Presentation %d: cooldown actif.', $presentation->getId()));
                    continue;
                }
            }

            $result = $this->embeddingService->buildForText($text, $hash);
            if ($result === null) {
                $io->warning(sprintf('Presentation %d: embedding non généré.', $presentation->getId()));
                continue;
            }

            if (!$existing) {
                $existing = new PresentationEmbedding($presentation, $result->model);
                $this->em->persist($existing);
            }

            $existing
                ->setDims($result->dimensions)
                ->setNormalized($result->normalized)
                ->setVectorBinary($result->vectorBinary)
                ->setContentHash($result->contentHash);

            $updatedPresentationIds[] = $presentation->getId();
            $processed++;
        }

        if ($processed > 0) {
            $this->em->flush();
        }

        if ($computeNeighbors && $updatedPresentationIds !== []) {
            $this->recomputeNeighbors($updatedPresentationIds, $model, $dims, $k, $includeUnpublished, $includeDeleted, $io);
        }

        $io->success(sprintf('Embeddings mis à jour: %d', $processed));

        return Command::SUCCESS;
    }

    /**
     * @return PPBase[]
     */
    private function loadPresentations(?string $presentationId, bool $includeUnpublished, bool $includeDeleted, int $limit): array
    {
        $repo = $this->em->getRepository(PPBase::class);

        if ($presentationId) {
            $presentation = $repo->find($presentationId);
            return $presentation ? [$presentation] : [];
        }

        $qb = $repo->createQueryBuilder('p');

        if (!$includeDeleted) {
            $qb->andWhere('p.isDeleted = false');
        }

        if (!$includeUnpublished) {
            $qb->andWhere('p.isPublished = true');
        }

        $qb->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    private function recomputeNeighbors(
        array $presentationIds,
        string $model,
        int $dims,
        int $k,
        bool $includeUnpublished,
        bool $includeDeleted,
        SymfonyStyle $io
    ): void {
        $qb = $this->em->createQueryBuilder()
            ->select('e', 'p')
            ->from(PresentationEmbedding::class, 'e')
            ->join('e.presentation', 'p')
            ->where('e.model = :model')
            ->andWhere('e.dims = :dims')
            ->setParameter('model', $model)
            ->setParameter('dims', $dims);

        if (!$includeDeleted) {
            $qb->andWhere('p.isDeleted = false');
        }

        if (!$includeUnpublished) {
            $qb->andWhere('p.isPublished = true');
        }

        /** @var PresentationEmbedding[] $embeddings */
        $embeddings = $qb->getQuery()->getResult();
        if ($embeddings === []) {
            $io->warning('Aucun embedding disponible pour calculer les voisins.');
            return;
        }

        $vectors = [];
        foreach ($embeddings as $embedding) {
            $presentationId = $embedding->getPresentation()->getId();
            if ($presentationId === null) {
                continue;
            }
            $vector = $this->unpackVector($embedding->getVectorBinary());
            if ($vector === []) {
                continue;
            }
            if (!$embedding->isNormalized()) {
                $vector = $this->normalizeVector($vector);
            }
            $vectors[$presentationId] = $vector;
        }

        if ($vectors === []) {
            $io->warning('Impossible de décoder les vecteurs.');
            return;
        }

        foreach ($presentationIds as $presentationId) {
            if (!isset($vectors[$presentationId])) {
                $io->writeln(sprintf('Presentation %d: pas de vecteur pour les voisins.', $presentationId));
                continue;
            }

            $scores = [];
            $sourceVector = $vectors[$presentationId];

            foreach ($vectors as $candidateId => $candidateVector) {
                if ($candidateId === $presentationId) {
                    continue;
                }
                $scores[$candidateId] = $this->dotProduct($sourceVector, $candidateVector);
            }

            if ($scores === []) {
                continue;
            }

            arsort($scores);
            $scores = array_slice($scores, 0, $k, true);

            $presentationRef = $this->em->getReference(PPBase::class, $presentationId);
            $this->em->createQueryBuilder()
                ->delete(PresentationNeighbor::class, 'n')
                ->where('n.presentation = :presentation')
                ->andWhere('n.model = :model')
                ->setParameter('presentation', $presentationRef)
                ->setParameter('model', $model)
                ->getQuery()
                ->execute();

            $rank = 1;
            foreach ($scores as $neighborId => $score) {
                $neighborRef = $this->em->getReference(PPBase::class, $neighborId);
                $neighbor = new PresentationNeighbor($presentationRef, $neighborRef, $model, $rank);
                $neighbor->setScore((float) $score);
                $this->em->persist($neighbor);
                $rank++;
            }

            $io->writeln(sprintf('Presentation %d: voisins recalculés (%d).', $presentationId, count($scores)));
        }

        $this->em->flush();
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
     * @return float[]
     */
    private function normalizeVector(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        $norm = sqrt($sum);
        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(static fn ($value): float => $value / $norm, $vector);
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    private function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }
}
