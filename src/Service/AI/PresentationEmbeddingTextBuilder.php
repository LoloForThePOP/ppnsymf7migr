<?php

namespace App\Service\AI;

use App\Entity\Category;
use App\Entity\PPBase;

final class PresentationEmbeddingTextBuilder
{
    private const MAX_TEXT_LENGTH = 6000;

    public function buildText(PPBase $presentation): string
    {
        $parts = [];

        $title = $this->normalizeText($presentation->getTitle());
        if ($title !== '') {
            $parts[] = $title;
        }

        $goal = $this->normalizeText($presentation->getGoal());
        if ($goal !== '') {
            $parts[] = $goal;
        }

        $description = $this->normalizeText($presentation->getTextDescription());
        if ($description !== '') {
            $parts[] = $description;
        }

        $categories = $this->normalizeList($this->extractCategories($presentation));
        if ($categories !== []) {
            $parts[] = implode(', ', $categories);
        }

        $keywords = $this->normalizeList($this->extractKeywords($presentation->getKeywords()));
        if ($keywords !== []) {
            $parts[] = implode(', ', $keywords);
        }

        $text = trim(implode("\n", $parts));
        if ($text === '') {
            return '';
        }

        return $this->truncate($text, self::MAX_TEXT_LENGTH);
    }

    public function hashText(string $text, bool $raw = true): string
    {
        return hash('sha256', $text, $raw);
    }

    /**
     * @return string[]
     */
    private function extractCategories(PPBase $presentation): array
    {
        $categories = [];
        foreach ($presentation->getCategories() as $category) {
            if (!$category instanceof Category) {
                continue;
            }
            $label = $this->normalizeText($category->getLabel() ?: $category->getUniqueName());
            if ($label !== '') {
                $categories[] = $label;
            }
        }

        return $categories;
    }

    /**
     * @return string[]
     */
    private function extractKeywords(?string $keywords): array
    {
        if ($keywords === null) {
            return [];
        }

        $chunks = preg_split('/[,;]+/u', $keywords) ?: [];
        $items = [];
        foreach ($chunks as $chunk) {
            $item = $this->normalizeText($chunk);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function normalizeText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    /**
     * @param string[] $items
     *
     * @return string[]
     */
    private function normalizeList(array $items): array
    {
        $items = array_map(static fn (string $item): string => trim($item), $items);
        $items = array_filter($items, static fn (string $item): bool => $item !== '');

        $unique = [];
        $seen = [];
        foreach ($items as $item) {
            $key = $this->lowercase($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return array_values($unique);
    }

    private function lowercase(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $maxLength) {
                return $value;
            }

            return mb_substr($value, 0, $maxLength);
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}
