<?php

namespace App\Service\Recommendation;

use App\Entity\PPBase;
use App\Entity\PresentationEmbedding;
use App\Entity\PresentationNeighbor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PresentationNeighborRecomputeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int[]
     */
    public function listPresentationIds(
        string $model,
        int $dims,
        bool $includeUnpublished,
        bool $includeDeleted,
        int $limit = 0
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.presentation) AS presentation_id')
            ->from(PresentationEmbedding::class, 'e')
            ->join('e.presentation', 'p')
            ->where('e.model = :model')
            ->andWhere('e.dims = :dims')
            ->setParameter('model', $model)
            ->setParameter('dims', $dims)
            ->orderBy('p.updatedAt', 'DESC');

        if (!$includeDeleted) {
            $qb->andWhere('(p.isDeleted = false OR p.isDeleted IS NULL)');
        }

        if (!$includeUnpublished) {
            $qb->andWhere('p.isPublished = true');
        }

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $presentationIds = [];
        foreach ($rows as $row) {
            $presentationId = (int) ($row['presentation_id'] ?? 0);
            if ($presentationId > 0) {
                $presentationIds[] = $presentationId;
            }
        }

        return $presentationIds;
    }

    /**
     * @param int[] $presentationIds
     *
     * @return array{
     *     updated: int,
     *     skippedMissingVector: int,
     *     candidates: int
     * }
     */
    public function recomputeForPresentationIds(
        array $presentationIds,
        string $model,
        int $dims,
        int $k,
        bool $includeUnpublished,
        bool $includeDeleted,
        ?SymfonyStyle $io = null
    ): array {
        $presentationIds = array_values(
            array_unique(
                array_filter(
                    array_map(static fn ($id): int => (int) $id, $presentationIds),
                    static fn (int $id): bool => $id > 0
                )
            )
        );

        if ($presentationIds === []) {
            return [
                'updated' => 0,
                'skippedMissingVector' => 0,
                'candidates' => 0,
            ];
        }

        $embeddings = $this->loadEmbeddings($model, $dims, $includeUnpublished, $includeDeleted);
        if ($embeddings === []) {
            $io?->warning('Aucun embedding disponible pour calculer les voisins.');
            return [
                'updated' => 0,
                'skippedMissingVector' => count($presentationIds),
                'candidates' => 0,
            ];
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
            $io?->warning('Impossible de décoder les vecteurs.');
            return [
                'updated' => 0,
                'skippedMissingVector' => count($presentationIds),
                'candidates' => 0,
            ];
        }

        $k = max(1, $k);
        $updated = 0;
        $skippedMissingVector = 0;

        foreach ($presentationIds as $presentationId) {
            if (!isset($vectors[$presentationId])) {
                $skippedMissingVector++;
                $io?->writeln(sprintf('Presentation %d: pas de vecteur pour les voisins.', $presentationId));
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

            $presentationRef = $this->entityManager->getReference(PPBase::class, $presentationId);
            $this->entityManager->createQueryBuilder()
                ->delete(PresentationNeighbor::class, 'n')
                ->where('n.presentation = :presentation')
                ->andWhere('n.model = :model')
                ->setParameter('presentation', $presentationRef)
                ->setParameter('model', $model)
                ->getQuery()
                ->execute();

            if ($scores === []) {
                $io?->writeln(sprintf('Presentation %d: aucun voisin disponible.', $presentationId));
                continue;
            }

            arsort($scores);
            $scores = array_slice($scores, 0, $k, true);

            $rank = 1;
            foreach ($scores as $neighborId => $score) {
                $neighborRef = $this->entityManager->getReference(PPBase::class, $neighborId);
                $neighbor = new PresentationNeighbor($presentationRef, $neighborRef, $model, $rank);
                $neighbor->setScore((float) $score);
                $this->entityManager->persist($neighbor);
                $rank++;
            }

            $updated++;
            $io?->writeln(sprintf('Presentation %d: voisins recalculés (%d).', $presentationId, count($scores)));
        }

        $this->entityManager->flush();

        return [
            'updated' => $updated,
            'skippedMissingVector' => $skippedMissingVector,
            'candidates' => count($vectors),
        ];
    }

    /**
     * @return PresentationEmbedding[]
     */
    private function loadEmbeddings(
        string $model,
        int $dims,
        bool $includeUnpublished,
        bool $includeDeleted
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e', 'p')
            ->from(PresentationEmbedding::class, 'e')
            ->join('e.presentation', 'p')
            ->where('e.model = :model')
            ->andWhere('e.dims = :dims')
            ->setParameter('model', $model)
            ->setParameter('dims', $dims);

        if (!$includeDeleted) {
            $qb->andWhere('(p.isDeleted = false OR p.isDeleted IS NULL)');
        }

        if (!$includeUnpublished) {
            $qb->andWhere('p.isPublished = true');
        }

        return $qb->getQuery()->getResult();
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
