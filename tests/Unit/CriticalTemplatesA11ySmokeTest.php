<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CriticalTemplatesA11ySmokeTest extends TestCase
{
    #[DataProvider('antiPatternProvider')]
    public function testCriticalTemplatesDoNotContainKnownA11yAntiPatterns(
        string $relativePath,
        string $pattern
    ): void {
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        self::assertFileExists($absolutePath, sprintf('Template missing: %s', $relativePath));

        $content = file_get_contents($absolutePath);
        self::assertIsString($content);
        $normalized = $this->stripTwigComments($content);

        self::assertDoesNotMatchRegularExpression(
            $pattern,
            $normalized,
            sprintf('A11y anti-pattern found in %s', $relativePath)
        );
    }

    private function stripTwigComments(string $template): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $template);
    }

    /**
     * @return iterable<string,array{0:string,1:string}>
     */
    public static function antiPatternProvider(): iterable
    {
        $criticalFiles = [
            'templates/_partials/header_navbar.html.twig',
            'templates/project_presentation/edit_show/bookmark_button.html.twig',
            'templates/project_presentation/edit_show/like_button.html.twig',
            'templates/project_presentation/edit_show/follow_button.html.twig',
            'templates/project_presentation/edit_show/misc_structure_container.html.twig',
            'templates/project_presentation/edit_show/upper_box_structure.html.twig',
            'templates/project_presentation/edit_show/categories_keywords/display.html.twig',
            'templates/project_presentation/edit_show/statuses/display.html.twig',
            'templates/project_presentation/edit_show/questions_answers/display.html.twig',
            'templates/project_presentation/edit_show/slides/_slides_container.html.twig',
            'templates/project_presentation/edit_show/slides/slides.html.twig',
            'templates/project_presentation/edit_show/slides/add_image.html.twig',
            'templates/project_presentation/edit_show/slides/update_image_slide.html.twig',
            'templates/project_presentation/create/_form.html.twig',
            'templates/home/_jumbotron.html.twig',
            'templates/comment/_macro.html.twig',
            'templates/article/edit.html.twig',
        ];

        $patterns = [
            // Avoid fake anchors that behave like buttons.
            '/<a\b[^>]*\bhref="#"/i',
            // Avoid invalid div-based dropdown triggers for keyboard users.
            '/<div\b[^>]*\bdata-bs-toggle="dropdown"/i',
            // Avoid nested interactive controls.
            '/<a\b[^>]*>\s*<button\b/i',
            // role="button" usually indicates wrong semantic element in this codebase.
            '/\brole="button"\b/i',
        ];

        foreach ($criticalFiles as $file) {
            foreach ($patterns as $pattern) {
                yield sprintf('%s :: %s', $file, $pattern) => [$file, $pattern];
            }
        }
    }
}
