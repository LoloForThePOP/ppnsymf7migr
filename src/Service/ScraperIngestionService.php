<?php

namespace App\Service;

use OpenAI;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fetches and normalizes scraped projects via the OpenAI client using the scraper prompt.
 *
 * This service only prepares normalized payloads; persisting to entities happens elsewhere.
 */
class ScraperIngestionService
{
    private Client $client;

    private const ALLOWED_CATEGORIES = [
        'software', 'science', 'inform', 'humane', 'animals', 'material', 'restore',
        'transport', 'environment', 'history', 'money', 'food', 'services', 'arts',
        'entertainment', 'data', 'health', 'idea', 'space', 'crisis',
    ];

    public function __construct(
        #[Autowire('%app.scraper.prompt_path%')]
        private readonly string $promptPath,
        #[Autowire('%app.scraper.model%')]
        private readonly string $model,
        #[Autowire(env: 'OPENAI_API_KEY')]
        string $apiKey,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Fetch raw JSON from OpenAI and return normalized items + errors.
     *
     * @return array{items: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function fetchAndNormalize(): array
    {
        $rawContent = $this->fetchRawContent();
        $decoded = json_decode($rawContent, true);

        if (!is_array($decoded)) {
            return [
                'items' => [],
                'errors' => ['JSON decode failed: ' . json_last_error_msg()],
            ];
        }

        $items = [];
        $errors = [];

        foreach ($decoded as $index => $payload) {
            if (!is_array($payload)) {
                $errors[] = sprintf('Item %d is not an object', $index);
                continue;
            }

            $normalized = $this->normalizeItem($payload);
            if (isset($normalized['error'])) {
                $errors[] = sprintf('Item %d: %s', $index, $normalized['error']);
                continue;
            }

            $items[] = $normalized;
        }

        return ['items' => $items, 'errors' => $errors];
    }

    private function fetchRawContent(): string
    {
        $prompt = file_get_contents($this->promptPath);

        if ($prompt === false) {
            throw new \RuntimeException(sprintf('Unable to read prompt file at %s', $this->promptPath));
        }

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un agent autonome de collecte de projets francophones.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '';

        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('Scraper response was empty.');
        }

        $this->logger->info('Scraper raw content length', ['length' => strlen($content)]);

        return $content;
    }

    /**
     * Normalize a single scraped item. Returns ['error' => '...'] on failure.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeItem(array $payload): array
    {
        $required = ['title', 'goal', 'source_url'];
        foreach ($required as $field) {
            if (empty($payload[$field]) || !is_string($payload[$field])) {
                return ['error' => sprintf('Missing or empty required field "%s"', $field)];
            }
        }

        $categories = $this->normalizeCategories($payload['categories'] ?? []);

        if (empty($categories)) {
            return ['error' => 'No valid categories'];
        }

        return [
            'id' => $payload['id'] ?? null,
            'title' => trim($payload['title']),
            'goal' => trim($payload['goal']),
            'description' => isset($payload['description']) && is_string($payload['description']) ? trim($payload['description']) : null,
            'categories' => $categories,
            'organization' => isset($payload['organization']) && is_string($payload['organization']) ? trim($payload['organization']) : null,
            'website' => isset($payload['website']) && is_string($payload['website']) && $payload['website'] !== ''
                ? trim($payload['website'])
                : trim($payload['source_url']),
            'country' => isset($payload['country']) && is_string($payload['country']) ? trim($payload['country']) : null,
            'city' => isset($payload['city']) && is_string($payload['city']) ? trim($payload['city']) : null,
            'tags' => $this->normalizeTags($payload['tags'] ?? []),
            'source_published_at' => $this->normalizeDate($payload['source_published_at'] ?? null),
            'language' => isset($payload['language']) && is_string($payload['language']) ? strtolower(trim($payload['language'])) : null,
            'image' => isset($payload['image']) && is_string($payload['image']) ? trim($payload['image']) : null,
            'source_url' => trim($payload['source_url']),
            'created_at' => $this->normalizeDateTime($payload['created_at'] ?? null),
            'status' => isset($payload['status']) && is_string($payload['status']) ? trim($payload['status']) : null,
            'status_reason' => isset($payload['status_reason']) && is_string($payload['status_reason']) ? trim($payload['status_reason']) : null,
            'websites' => $this->normalizeWebsites($payload['websites'] ?? []),
        ];
    }

    /**
     * @param mixed $categories
     * @return array<int, string>
     */
    private function normalizeCategories(mixed $categories): array
    {
        if (!is_array($categories)) {
            return [];
        }

        $normalized = [];
        foreach ($categories as $cat) {
            if (!is_string($cat)) {
                continue;
            }
            $value = strtolower(trim($cat));
            if (in_array($value, self::ALLOWED_CATEGORIES, true) && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
            if (count($normalized) >= 3) {
                break;
            }
        }

        if (empty($normalized) && in_array('services', self::ALLOWED_CATEGORIES, true)) {
            $normalized[] = 'services';
        }

        return $normalized;
    }

    /**
     * @param mixed $tags
     * @return array<int, string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $value = trim($tag);
            if ($value !== '' && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $websites
     * @return array<int, array{title: string, url: string}>
     */
    private function normalizeWebsites(mixed $websites): array
    {
        if (!is_array($websites)) {
            return [];
        }

        $normalized = [];

        foreach ($websites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $title = $site['title'] ?? null;
            $url = $site['url'] ?? null;

            if (!is_string($title) || !is_string($url) || $title === '' || $url === '') {
                continue;
            }

            $normalized[] = [
                'title' => trim($title),
                'url' => trim($url),
            ];

            if (count($normalized) >= 3) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date ?: null;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
