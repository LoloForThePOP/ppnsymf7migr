<?php

namespace App\Service\AI;

use App\Entity\PPBase;
use OpenAI\Client;
use OpenAI\Factory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PresentationEmbeddingService
{
    private ?Client $client;

    public function __construct(
        private readonly PresentationEmbeddingTextBuilder $textBuilder,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::OPENAI_EMBEDDING_MODEL)%')]
        private readonly string $model = 'text-embedding-3-small',
        #[Autowire('%env(int:default::OPENAI_EMBEDDING_DIMENSIONS)%')]
        private readonly int $dimensions = 512,
        #[Autowire('%env(default::OPENAI_API_KEY)%')]
        ?string $apiKey = null,
        #[Autowire('%env(default::OPENAI_BASE_URI)%')]
        ?string $baseUri = null,
    ) {
        $apiKey = $apiKey ? trim($apiKey) : '';

        if ($apiKey === '') {
            $this->client = null;
            return;
        }

        $factory = (new Factory())->withApiKey($apiKey);
        if ($baseUri) {
            $factory = $factory->withBaseUri($baseUri);
        }

        $this->client = $factory->make();
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function buildForPresentation(PPBase $presentation): ?PresentationEmbeddingResult
    {
        $text = $this->textBuilder->buildText($presentation);
        if ($text === '') {
            return null;
        }

        $hash = $this->textBuilder->hashText($text, true);

        return $this->buildForText($text, $hash);
    }

    public function buildForText(string $text, ?string $contentHash = null): ?PresentationEmbeddingResult
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if ($this->client === null) {
            $this->logger->warning('Embedding client not configured.');
            return null;
        }

        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];

        if ($this->dimensions > 0) {
            $payload['dimensions'] = $this->dimensions;
        }

        try {
            $response = $this->client->embeddings()->create($payload);
            $embedding = $response->embeddings[0]->embedding ?? null;
        } catch (\Throwable $exception) {
            $this->logger->error('Embedding generation failed.', [
                'exception' => $exception,
            ]);
            return null;
        }

        if (!is_array($embedding) || $embedding === []) {
            $this->logger->warning('Embedding response is empty.');
            return null;
        }

        [$vector, $normalized] = $this->normalizeVector($embedding);
        $vectorBinary = $this->packVector($vector);

        return new PresentationEmbeddingResult(
            $this->model,
            count($vector),
            $normalized,
            $vector,
            $vectorBinary,
            $contentHash ?? $this->textBuilder->hashText($text, true),
            $text,
        );
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
