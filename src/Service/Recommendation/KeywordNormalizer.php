<?php

namespace App\Service\Recommendation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class KeywordNormalizer
{
    private const MIN_LENGTH = 2;
    private const MAX_LENGTH = 60;

    /**
     * @var array<string,bool>
     */
    private array $stopwordIndex = [];

    /**
     * @var array<string,string>
     */
    private array $aliasIndex = [];

    /**
     * @param string[]              $stopwords
     * @param array<string,string[]> $aliases
     */
    public function __construct(
        #[Autowire('%app.recommendation.keyword.stopwords%')]
        array $stopwords = [],
        #[Autowire('%app.recommendation.keyword.aliases%')]
        array $aliases = [],
    ) {
        foreach ($stopwords as $stopword) {
            $normalized = $this->normalizeForIndex((string) $stopword);
            if ($normalized !== '') {
                $this->stopwordIndex[$normalized] = true;
            }
        }

        foreach ($aliases as $canonical => $variants) {
            $canonicalNorm = $this->normalizeForIndex((string) $canonical);
            if ($canonicalNorm === '') {
                continue;
            }
            $this->aliasIndex[$canonicalNorm] = $canonicalNorm;

            if (!is_array($variants)) {
                continue;
            }

            foreach ($variants as $variant) {
                $variantNorm = $this->normalizeForIndex((string) $variant);
                if ($variantNorm === '') {
                    continue;
                }

                $this->aliasIndex[$variantNorm] = $canonicalNorm;
            }
        }
    }

    /**
     * @param array<int,string> $keywords
     *
     * @return string[]
     */
    public function normalizeKeywordArray(array $keywords, int $limit = 12): array
    {
        $limit = max(1, $limit);
        $normalized = [];

        foreach ($keywords as $keyword) {
            $item = $this->normalizeKeyword((string) $keyword);
            if ($item === null) {
                continue;
            }

            $normalized[$item] = true;
            if (count($normalized) >= $limit) {
                break;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @return string[]
     */
    public function normalizeRawKeywords(?string $rawKeywords, int $limit = 12): array
    {
        $rawKeywords = trim((string) $rawKeywords);
        if ($rawKeywords === '') {
            return [];
        }

        $parts = preg_split('/[,;|]+/u', $rawKeywords) ?: [];
        $parts = array_map(static fn (string $part): string => trim($part), $parts);

        return $this->normalizeKeywordArray($parts, $limit);
    }

    public function normalizeKeyword(string $keyword): ?string
    {
        $normalized = $this->normalizeForIndex($keyword);
        if ($normalized === '') {
            return null;
        }

        if (isset($this->stopwordIndex[$normalized])) {
            return null;
        }

        $normalized = $this->aliasIndex[$normalized] ?? $normalized;

        if (isset($this->stopwordIndex[$normalized])) {
            return null;
        }

        return $normalized;
    }

    private function normalizeForIndex(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = $this->toLower($value);
        $value = $this->stripAccents($value);
        $value = str_replace(['’', "'", '`', '´'], ' ', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s\-_]+/u', ' ', $value) ?? '';
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        if ($value === '') {
            return '';
        }

        $value = $this->singularizePhrase($value);
        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return '';
        }

        return $value;
    }

    private function singularizePhrase(string $value): string
    {
        $tokens = preg_split('/\s+/u', $value) ?: [];
        $normalizedTokens = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $singular = $this->singularizeWord($token);
            $normalizedTokens[] = $singular;
        }

        return trim(implode(' ', $normalizedTokens));
    }

    private function singularizeWord(string $word): string
    {
        $length = mb_strlen($word);
        if ($length <= 3) {
            return $word;
        }

        if (str_ends_with($word, 'ies') && $length > 4) {
            return mb_substr($word, 0, -3) . 'y';
        }

        if (preg_match('/(us|is|ss)$/u', $word)) {
            return $word;
        }

        if (str_ends_with($word, 's')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    private function toLower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }

    private function stripAccents(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $trans = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
            if ($trans instanceof \Transliterator) {
                $stripped = $trans->transliterate($value);
                if (is_string($stripped)) {
                    return $stripped;
                }
            }
        }

        $iconv = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '') {
            return $iconv;
        }

        return $value;
    }
}
