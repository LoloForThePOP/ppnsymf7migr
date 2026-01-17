<?php

namespace App\Service;

// Resolve platform-specific favicon slugs used by the crowdfunding summary row.
// Extend PLATFORM_ICON_MAP/HOST_ICON_MAP and add the icon file to popular_websites/ to support new platforms.
class PlatformIconResolver
{
    private const PLATFORM_ICON_MAP = [
        'ulule' => 'ulule',
        'ulule.fr' => 'ulule',
        'ulule.com' => 'ulule',
    ];

    private const HOST_ICON_MAP = [
        'ulule.com' => 'ulule',
        'ulule.fr' => 'ulule',
    ];

    public function resolve(?string $platform, ?string $sourceUrl = null): ?string
    {
        $icon = $this->resolveFromPlatform($platform);
        if ($icon !== null) {
            return $icon;
        }

        return $this->resolveFromUrl($sourceUrl);
    }

    private function resolveFromPlatform(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $normalized = strtolower(trim($platform));
        if ($normalized === '') {
            return null;
        }

        if (isset(self::PLATFORM_ICON_MAP[$normalized])) {
            return self::PLATFORM_ICON_MAP[$normalized];
        }

        $compact = preg_replace('/\s+/', '', $normalized);
        if ($compact && isset(self::PLATFORM_ICON_MAP[$compact])) {
            return self::PLATFORM_ICON_MAP[$compact];
        }

        if (str_contains($normalized, 'ulule')) {
            return 'ulule';
        }

        return null;
    }

    private function resolveFromUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return null;
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/^(www\.|m\.)/i', '', $host);

        if (isset(self::HOST_ICON_MAP[$host])) {
            return self::HOST_ICON_MAP[$host];
        }

        if ($this->isUluleHost($host)) {
            return 'ulule';
        }

        return null;
    }

    private function isUluleHost(string $host): bool
    {
        return $host === 'ulule.com'
            || $host === 'ulule.fr'
            || str_ends_with($host, '.ulule.com')
            || str_ends_with($host, '.ulule.fr');
    }
}
