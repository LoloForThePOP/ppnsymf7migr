<?php

namespace App\Service\HomeFeed\Signal;

final class CategorySignals
{
    /**
     * @param string[] $primaryCategories
     * @param string[] $fallbackCategories
     */
    public function __construct(
        private readonly array $primaryCategories,
        private readonly bool $primaryFromPreferences = false,
        private readonly array $fallbackCategories = [],
    ) {
    }

    /**
     * @return string[]
     */
    public function getPrimaryCategories(): array
    {
        return $this->normalizeSlugs($this->primaryCategories);
    }

    public function isPrimaryFromPreferences(): bool
    {
        return $this->primaryFromPreferences;
    }

    /**
     * @return string[]
     */
    public function getFallbackCategories(): array
    {
        return $this->normalizeSlugs($this->fallbackCategories);
    }

    /**
     * @param string[] $slugs
     *
     * @return string[]
     */
    private function normalizeSlugs(array $slugs): array
    {
        $normalized = [];

        foreach ($slugs as $slug) {
            $token = strtolower(trim((string) $slug));
            if ($token === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $token)) {
                continue;
            }

            $normalized[$token] = true;
        }

        return array_keys($normalized);
    }
}

