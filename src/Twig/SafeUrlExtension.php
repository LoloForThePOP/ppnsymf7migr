<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SafeUrlExtension extends AbstractExtension
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_href', [$this, 'safeHref']),
        ];
    }

    public function safeHref(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        return $url;
    }
}
