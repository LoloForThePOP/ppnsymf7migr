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
        'fondation du patrimoine' => 'fondation-patrimoine',
        'fondation-patrimoine' => 'fondation-patrimoine',
        'fondationpatrimoine' => 'fondation-patrimoine',
        'fondation-patrimoine.org' => 'fondation-patrimoine',
    ];

    private const PLATFORM_LABEL_MAP = [
        'ulule' => 'Ulule',
        'ulule.fr' => 'Ulule',
        'ulule.com' => 'Ulule',
        'fondation du patrimoine' => 'Fondation du patrimoine',
        'fondation-patrimoine' => 'Fondation du patrimoine',
        'fondationpatrimoine' => 'Fondation du patrimoine',
        'fondation-patrimoine.org' => 'Fondation du patrimoine',
    ];

    private const ICON_LABEL_MAP = [
        'ulule' => 'Ulule',
        'fondation-patrimoine' => 'Fondation du patrimoine',
    ];

    private const HOST_ICON_MAP = [
        'ulule.com' => 'ulule',
        'ulule.fr' => 'ulule',
        'fondation-patrimoine.org' => 'fondation-patrimoine',
    ];

    public function resolve(?string $platform, ?string $sourceUrl = null): ?string
    {
        $icon = $this->resolveFromPlatform($platform);
        if ($icon !== null) {
            return $icon;
        }

        return $this->resolveFromUrl($sourceUrl);
    }

    public function resolveLabel(?string $platform, ?string $sourceUrl = null): ?string
    {
        $label = $this->resolveLabelFromPlatform($platform);
        if ($label !== null) {
            return $label;
        }

        $icon = $this->resolve($platform, $sourceUrl);
        if ($icon !== null && isset(self::ICON_LABEL_MAP[$icon])) {
            return self::ICON_LABEL_MAP[$icon];
        }

        return null;
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

        if (str_contains($normalized, 'fondation') && str_contains($normalized, 'patrimoine')) {
            return 'fondation-patrimoine';
        }

        return null;
    }

    private function resolveLabelFromPlatform(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $label = trim($platform);
        if ($label === '') {
            return null;
        }

        $normalized = strtolower($label);
        if (isset(self::PLATFORM_LABEL_MAP[$normalized])) {
            return self::PLATFORM_LABEL_MAP[$normalized];
        }

        $compact = preg_replace('/\s+/', '', $normalized);
        if ($compact && isset(self::PLATFORM_LABEL_MAP[$compact])) {
            return self::PLATFORM_LABEL_MAP[$compact];
        }

        if (str_contains($normalized, 'ulule')) {
            return self::PLATFORM_LABEL_MAP['ulule'];
        }

        if (str_contains($normalized, 'fondation') && str_contains($normalized, 'patrimoine')) {
            return self::PLATFORM_LABEL_MAP['fondation du patrimoine'];
        }

        return $label;
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

        if ($this->isFondationPatrimoineHost($host)) {
            return 'fondation-patrimoine';
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

    private function isFondationPatrimoineHost(string $host): bool
    {
        return $host === 'fondation-patrimoine.org'
            || str_ends_with($host, '.fondation-patrimoine.org');
    }
}
