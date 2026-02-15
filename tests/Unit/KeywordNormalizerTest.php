<?php

namespace App\Tests\Unit;

use App\Service\Recommendation\KeywordNormalizer;
use PHPUnit\Framework\TestCase;

final class KeywordNormalizerTest extends TestCase
{
    public function testNormalizesPluralAndDeduplicates(): void
    {
        $normalizer = new KeywordNormalizer();

        $keywords = $normalizer->normalizeRawKeywords('Films, film,  films ');

        self::assertSame(['film'], $keywords);
    }

    public function testNormalizesAccentsAliasesAndStopwords(): void
    {
        $normalizer = new KeywordNormalizer(
            ['projet', 'projets'],
            [
                'ia' => ['ai', 'intelligence artificielle'],
                'ecologie' => ['ecologique', 'ecologiques'],
            ]
        );

        $keywords = $normalizer->normalizeRawKeywords(
            'Intelligence artificielle, AI, écologiques, Projets, projet'
        );

        self::assertSame(['ia', 'ecologie'], $keywords);
    }

    public function testKeepsMeaningfulNormalizedPhrases(): void
    {
        $normalizer = new KeywordNormalizer();

        $keywords = $normalizer->normalizeRawKeywords('Films-documentaires, vélo solidaire');

        self::assertSame(['film documentaire', 'velo solidaire'], $keywords);
    }
}
