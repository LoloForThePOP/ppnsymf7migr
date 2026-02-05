<?php

namespace App\Service\Scraping\JeVeuxAider;

final class JeVeuxAiderNuxtDataExtractor
{
    /**
     * @return array{payload: array<string, mixed>, debug: array<string, mixed>}|null
     */
    public function extract(string $html): ?array
    {
        $json = $this->extractNuxtDataJson($html);
        if ($json === null) {
            return null;
        }

        try {
            $raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($raw)) {
            return null;
        }

        $structureIndex = $this->findStructureIndex($raw);
        if ($structureIndex === null) {
            return null;
        }

        $resolver = new NuxtValueResolver($raw);
        $structure = $resolver->resolveIndex($structureIndex);
        if (!is_array($structure)) {
            return null;
        }

        $payload = $this->buildPayload($structure);

        return [
            'payload' => $payload,
            'debug' => [
                'structure_id' => $payload['id'] ?? null,
                'structure_name' => $payload['name'] ?? null,
                'domaines' => array_map(
                    static fn (array $domain): ?string => is_string($domain['slug'] ?? null) ? $domain['slug'] : null,
                    $payload['domaines'] ?? []
                ),
            ],
        ];
    }

    private function extractNuxtDataJson(string $html): ?string
    {
        if (!preg_match('#<script[^>]*id=["\']__NUXT_DATA__["\'][^>]*>(.*?)</script>#s', $html, $matches)) {
            return null;
        }

        $json = trim((string) ($matches[1] ?? ''));
        if ($json === '') {
            return null;
        }

        return html_entity_decode($json, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    }

    /**
     * @param array<int, mixed> $raw
     */
    private function findStructureIndex(array $raw): ?int
    {
        foreach ($raw as $index => $value) {
            if (!is_array($value) || !$this->isAssocArray($value)) {
                continue;
            }

            if ($this->hasKeys($value, ['name', 'address', 'zip', 'city']) && array_key_exists('description', $value)) {
                return is_int($index) ? $index : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(array $structure): array
    {
        $domaines = $this->normalizeDomaines($structure['domaines'] ?? null);

        return [
            'id' => $structure['id'] ?? null,
            'name' => $structure['name'] ?? null,
            'description' => $structure['description'] ?? null,
            'address' => $structure['address'] ?? null,
            'zip' => $structure['zip'] ?? null,
            'city' => $structure['city'] ?? null,
            'country' => $structure['country'] ?? null,
            'latitude' => $structure['latitude'] ?? null,
            'longitude' => $structure['longitude'] ?? null,
            'website' => $structure['website'] ?? null,
            'facebook' => $structure['facebook'] ?? null,
            'instagram' => $structure['instagram'] ?? null,
            'phone' => $structure['phone'] ?? null,
            'email' => $structure['email'] ?? null,
            'full_url' => $structure['full_url'] ?? null,
            'full_address' => $structure['full_address'] ?? null,
            'created_at' => $structure['created_at'] ?? null,
            'updated_at' => $structure['updated_at'] ?? null,
            'publics_beneficiaires' => $structure['publics_beneficiaires'] ?? [],
            'domaines' => $domaines,
            'logo_url' => $this->extractMediaUrl($structure['logo'] ?? null),
            'illustration_urls' => $this->extractMediaUrls($structure['illustrations'] ?? null),
        ];
    }

    /**
     * @return array<int, array{name: ?string, slug: ?string, title: ?string}>
     */
    private function normalizeDomaines(mixed $domaines): array
    {
        if (!is_array($domaines)) {
            return [];
        }

        $items = [];
        foreach ($domaines as $domaine) {
            if (!is_array($domaine)) {
                continue;
            }

            $items[] = [
                'name' => is_string($domaine['name'] ?? null) ? $domaine['name'] : null,
                'slug' => is_string($domaine['slug'] ?? null) ? $domaine['slug'] : null,
                'title' => is_string($domaine['title'] ?? null) ? $domaine['title'] : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function extractMediaUrls(mixed $media): array
    {
        if ($media === null) {
            return [];
        }

        $urls = [];
        if (is_array($media) && $this->isAssocArray($media)) {
            $url = $this->extractMediaUrl($media);
            if ($url !== null) {
                $urls[] = $url;
            }

            return array_values(array_unique($urls));
        }

        if (is_array($media)) {
            foreach ($media as $entry) {
                $url = $this->extractMediaUrl($entry);
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function extractMediaUrl(mixed $media): ?string
    {
        if (!is_array($media)) {
            return null;
        }

        $urls = $media['urls'] ?? null;
        if (is_array($urls)) {
            foreach (['logo', 'large', 'media_library_original', 'original', 'thumb', 'small'] as $key) {
                $value = $urls[$key] ?? null;
                if (is_string($value) && str_starts_with($value, 'http')) {
                    return $value;
                }
            }

            foreach ($urls as $value) {
                if (is_string($value) && str_starts_with($value, 'http')) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssocArray(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!is_int($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<int, string> $keys
     */
    private function hasKeys(array $value, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }
}

final class NuxtValueResolver
{
    /**
     * @var array<int, mixed>
     */
    private array $raw;
    /**
     * @var array<int, mixed>
     */
    private array $resolved = [];

    /**
     * @param array<int, mixed> $raw
     */
    public function __construct(array $raw)
    {
        $this->raw = $raw;
    }

    public function resolveIndex(int $index): mixed
    {
        if (array_key_exists($index, $this->resolved)) {
            return $this->resolved[$index];
        }

        if (!array_key_exists($index, $this->raw)) {
            return null;
        }

        $value = $this->raw[$index];
        $resolved = $this->resolveValue($value);
        $this->resolved[$index] = $resolved;

        return $resolved;
    }

    private function resolveValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isWrapper($value)) {
            return $this->resolveWrapper($value);
        }

        if ($this->isAssocArray($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->resolveReference($item);
            }

            return $out;
        }

        $out = [];
        foreach ($value as $item) {
            $out[] = $this->resolveReference($item);
        }

        return $out;
    }

    private function resolveReference(mixed $value): mixed
    {
        if (is_int($value)) {
            return $this->resolveIndex($value);
        }

        return $this->resolveValue($value);
    }

    /**
     * @param array<mixed> $value
     */
    private function resolveWrapper(array $value): mixed
    {
        $tag = $value[0] ?? null;
        $arg = $value[1] ?? null;

        switch ($tag) {
            case 'Reactive':
            case 'ShallowReactive':
            case 'Ref':
                return is_int($arg) ? $this->resolveIndex($arg) : $this->resolveValue($arg);
            case 'EmptyRef':
            case 'Undefined':
                return null;
            case 'Set':
                if (!is_array($arg)) {
                    return [];
                }
                $items = [];
                foreach ($arg as $entry) {
                    $items[] = $this->resolveReference($entry);
                }
                return $items;
            case 'Map':
                if (!is_array($arg)) {
                    return [];
                }
                $map = [];
                for ($i = 0; $i < count($arg); $i += 2) {
                    $key = $this->resolveReference($arg[$i] ?? null);
                    $val = $this->resolveReference($arg[$i + 1] ?? null);
                    if (is_string($key) || is_int($key)) {
                        $map[$key] = $val;
                    }
                }
                return $map;
            case 'Date':
                return $this->resolveReference($arg);
        }

        return $this->resolveReference($arg);
    }

    /**
     * @param array<mixed> $value
     */
    private function isWrapper(array $value): bool
    {
        if (!isset($value[0]) || !is_string($value[0])) {
            return false;
        }

        return in_array($value[0], [
            'Reactive',
            'ShallowReactive',
            'Ref',
            'EmptyRef',
            'Undefined',
            'Set',
            'Map',
            'Date',
        ], true);
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssocArray(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!is_int($key)) {
                return true;
            }
        }

        return false;
    }
}
