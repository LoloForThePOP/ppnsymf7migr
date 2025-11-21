<?php

namespace App\Service\AI;

use App\Entity\PPBase;
use App\Repository\CategoryRepository;
use OpenAI\Client;
use OpenAI\Factory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates suggested categories and keywords for a project presentation via an OpenAI-compatible model.
 */
class ProjectTaggingService
{
    /**
     * @var Client|null
     */
    private ?Client $client;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(OPENAI_MODEL)%')]
        private readonly string $model = 'gpt-4o-mini',
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

    /**
     * Apply AI suggestions (or fallbacks) to the presentation, while keeping the entity consistent.
     *
     * @return array{categories: string[], keywords: string[]}
     */
    public function suggestAndApply(PPBase $presentation): array
    {
        $allowedCategories = $this->loadAllowedCategories();
        $suggestions = $this->client
            ? $this->suggestWithAI($presentation, $allowedCategories)
            : ['categories' => [], 'keywords' => []];

        if ($suggestions['categories'] === [] && $suggestions['keywords'] === []) {
            $suggestions['keywords'] = $this->fallbackKeywords($presentation);
            $suggestions['categories'] = $this->fallbackCategories($allowedCategories, $suggestions['keywords']);
        }

        $this->applyCategories($presentation, $suggestions['categories']);
        $this->applyKeywords($presentation, $suggestions['keywords']);

        return $suggestions;
    }

    /**
     * @return array<string, string> keyed by uniqueName with human label as value
     */
    private function loadAllowedCategories(): array
    {
        $categories = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            if (!$category->getUniqueName()) {
                continue;
            }
            $categories[$category->getUniqueName()] = $category->getLabel() ?? $category->getUniqueName();
        }

        return $categories;
    }

    /**
     * @param array<string,string> $allowedCategories
     *
     * @return array{categories: string[], keywords: string[]}
     */
    private function suggestWithAI(PPBase $presentation, array $allowedCategories): array
    {
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => implode("\n", [
                        'You are an assistant that chooses relevant project categories and keywords.',
                        'Pick up to 3 categories that best match the project from the allowed list below.',
                        'Allowed categories (uniqueName: label):',
                        json_encode($allowedCategories, JSON_THROW_ON_ERROR),
                        'Pick up to 10 concise keywords (1-3 words), no hashtags, no duplicates.',
                        'Return ONLY JSON with keys "categories" (array of uniqueName) and "keywords" (array of strings).',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'title' => $presentation->getTitle(),
                        'goal' => $presentation->getGoal(),
                        'description' => strip_tags((string) $presentation->getTextDescription()),
                        'existingKeywords' => $presentation->getKeywords(),
                    ], JSON_THROW_ON_ERROR),
                ],
            ];

            /** @var array{choices: array<int, array{message: array{content: string}}>} $response */
            $response = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $categories = $this->filterCategories($decoded['categories'] ?? [], $allowedCategories);
            $keywords = $this->normalizeKeywords($decoded['keywords'] ?? []);

            return [
                'categories' => $categories,
                'keywords' => $keywords,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('AI tagging failed, using fallback.', ['exception' => $exception]);

            return ['categories' => [], 'keywords' => []];
        }
    }

    /**
     * @param string[] $proposed
     * @param array<string,string> $allowed
     *
     * @return string[]
     */
    private function filterCategories(array $proposed, array $allowed): array
    {
        $proposed = array_values(array_unique(array_map('strval', $proposed)));
        $filtered = array_values(array_filter($proposed, static fn (string $id) => array_key_exists($id, $allowed)));

        return array_slice($filtered, 0, 3);
    }

    /**
     * @param string[] $keywords
     *
     * @return string[]
     */
    private function normalizeKeywords(array $keywords): array
    {
        $keywords = array_map(static fn ($keyword) => trim((string) $keyword), $keywords);
        $keywords = array_filter($keywords, static fn (string $keyword) => $keyword !== '');
        $keywords = array_values(array_unique($keywords));

        return array_slice($keywords, 0, 10);
    }

    /**
     * @return string[]
     */
    private function fallbackKeywords(PPBase $presentation): array
    {
        $text = strtolower(implode(' ', [
            $presentation->getTitle(),
            $presentation->getGoal(),
            strip_tags((string) $presentation->getTextDescription()),
        ]));

        $tokens = preg_split('/[^a-z0-9àâäéèêëîïôöùûüç-]+/iu', (string) $text) ?: [];
        $stopwords = ['les', 'des', 'une', 'dans', 'avec', 'pour', 'qui', 'que', 'est', 'sur', 'par', 'and', 'the', 'from', 'this', 'that'];

        $counts = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (strlen($token) < 3 || in_array($token, $stopwords, true)) {
                continue;
            }
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 8);
    }

    /**
     * @param array<string,string> $allowedCategories
     * @param string[]             $keywords
     *
     * @return string[]
     */
    private function fallbackCategories(array $allowedCategories, array $keywords): array
    {
        $matches = [];
        foreach ($allowedCategories as $id => $label) {
            foreach ($keywords as $keyword) {
                if (stripos($label, $keyword) !== false || stripos($id, $keyword) !== false) {
                    $matches[] = $id;
                    break;
                }
            }
        }

        return array_slice(array_values(array_unique($matches)), 0, 3);
    }

    /**
     * @param string[] $categoryIds
     */
    private function applyCategories(PPBase $presentation, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        // Do not override if user already set categories.
        if ($presentation->getCategories()->count() > 0) {
            return;
        }

        $categories = $this->categoryRepository->findBy(['uniqueName' => $categoryIds]);
        foreach ($categories as $category) {
            $presentation->addCategory($category);
        }
    }

    /**
     * @param string[] $keywords
     */
    private function applyKeywords(PPBase $presentation, array $keywords): void
    {
        if ($keywords === []) {
            return;
        }

        if ($presentation->getKeywords()) {
            return;
        }

        $presentation->setKeywords(implode(', ', $keywords));
    }
}
