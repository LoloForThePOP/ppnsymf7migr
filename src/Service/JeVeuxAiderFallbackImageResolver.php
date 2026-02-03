<?php

namespace App\Service;

use OpenAI;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JeVeuxAiderFallbackImageResolver
{
    private const PROMPT_RELATIVE_PATH = '/var/collect/url_lists/je_veux_aider/standard_image_selector_prompt.txt';
    /**
     * @var array<int, string>
     */
    private const ALLOWED_FOLDERS = [
        'administrative',
        'health',
        'education_or_inform',
        'legal_advice',
        'loneliness',
        'home_care',
        'homeless',
        'physical_activity',
        'athletism',
        'stadium',
        'community_support',
        'coaching_guidance',
        'citizenship',
        'craft_activities',
        'general_animals',
        'cats',
        'dogs',
        'nature',
        'sea',
        'talk_and_support',
        'social_inclusion',
        'volunteer_distribution',
        'fallback',
    ];

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly string $appScraperModel,
    ) {
    }

    /**
     * @param array<string, mixed> $aiPayload
     * @param array<string, mixed>|null $structuredData
     */
    public function resolve(array $aiPayload, ?array $structuredData = null): string
    {
        return $this->resolveWithDebug($aiPayload, $structuredData)['folder'];
    }

    /**
     * @param array<string, mixed> $aiPayload
     * @param array<string, mixed>|null $structuredData
     * @return array{folder:string,raw:?string,error:?string}
     */
    public function resolveWithDebug(array $aiPayload, ?array $structuredData = null): array
    {
        $prompt = $this->loadPrompt();
        if ($prompt === null) {
            return [
                'folder' => 'fallback',
                'raw' => null,
                'error' => 'Prompt de sélection introuvable.',
            ];
        }

        try {
            $raw = $this->askModel($prompt, $this->buildUserContent($aiPayload, $structuredData));
        } catch (\Throwable $e) {
            return [
                'folder' => 'fallback',
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'folder' => $this->extractFolderFromResponse($raw),
            'raw' => $raw,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $aiPayload
     * @param array<string, mixed>|null $structuredData
     */
    private function buildUserContent(array $aiPayload, ?array $structuredData): string
    {
        $payload = [
            'allowed_folders' => self::ALLOWED_FOLDERS,
            'project' => [
                'title' => $this->shortText($aiPayload['title'] ?? null, 280),
                'goal' => $this->shortText($aiPayload['goal'] ?? null, 320),
                'description_text' => $this->shortText(strip_tags((string) ($aiPayload['description_html'] ?? '')), 2800),
                'keywords' => $this->collectStringList($aiPayload['keywords'] ?? null),
                'categories' => $this->collectStringList($aiPayload['categories'] ?? null),
                'source_url' => $this->shortText($aiPayload['source_url'] ?? null, 500),
            ],
            'structured_data' => [
                'name' => $this->shortText($structuredData['name'] ?? null, 280),
                'description' => $this->shortText($structuredData['description'] ?? null, 2800),
                'domaines' => $this->collectNestedLabelList($structuredData['domaines'] ?? null),
                'publics_beneficiaires' => $this->collectStringList($structuredData['publics_beneficiaires'] ?? null),
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '{}';
    }

    private function askModel(string $prompt, string $userContent): string
    {
        $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
        $response = $client->chat()->create([
            'model' => $this->appScraperModel,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        return trim((string) ($response->choices[0]->message->content ?? ''));
    }

    private function loadPrompt(): ?string
    {
        $path = rtrim($this->projectDir, '/') . self::PROMPT_RELATIVE_PATH;
        if (!is_file($path)) {
            return null;
        }

        $prompt = file_get_contents($path);
        if ($prompt === false) {
            return null;
        }

        $prompt = trim($prompt);

        return $prompt === '' ? null : $prompt;
    }

    private function extractFolderFromResponse(string $raw): string
    {
        if ($raw === '') {
            return 'fallback';
        }

        $clean = trim($raw);
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $clean) ?? $clean;
            $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
            $clean = trim($clean);
        }

        $candidate = null;
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            $value = $decoded['standard_image_fallback_name'] ?? null;
            if (is_string($value)) {
                $candidate = $value;
            }
        }

        if (!is_string($candidate) || trim($candidate) === '') {
            foreach (self::ALLOWED_FOLDERS as $folder) {
                if (preg_match('/\b' . preg_quote($folder, '/') . '\b/i', $clean) === 1) {
                    $candidate = $folder;
                    break;
                }
            }
        }

        $candidate = strtolower(trim((string) $candidate));
        if (!in_array($candidate, self::ALLOWED_FOLDERS, true)) {
            return 'fallback';
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    private function collectStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $output = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }
            $output[] = $this->shortText($entry, 120);
        }

        return array_values(array_unique($output));
    }

    /**
     * @return array<int, string>
     */
    private function collectNestedLabelList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $output = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['slug', 'name', 'title'] as $key) {
                $candidate = $entry[$key] ?? null;
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }
                $output[] = $this->shortText($candidate, 120);
            }
        }

        return array_values(array_unique($output));
    }

    private function shortText(mixed $value, int $maxLen): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($value, 'UTF-8') > $maxLen) {
                $value = mb_substr($value, 0, $maxLen, 'UTF-8');
            }
        } elseif (strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
        }

        return $value;
    }
}
