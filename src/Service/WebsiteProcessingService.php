<?php

namespace App\Service;

use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;


class WebsiteProcessingService
{
    private const KNOWN_LOGOS = [
        "youtube.com",
        "linkedin.com",
        "facebook.com",
        "instagram.com",
        "twitch.tv",
        "twitter.com",
        "discord.gg",
        "discord.com",
        "github.com",
        "tiktok.com",
        "trello.com",
        "pinterest.fr",
        "pinterest.com",
        "itch.io",
        "gamejolt.com",
        "wikipedia.org",
        "fondation-patrimoine.org",
        "jeveuxaider.gouv.fr",
        "ulule.com",
        "ulule.fr",
    ];

    private const FALLBACK_ICONS = [
        'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8'
    ];

    /**
     * Process and normalize a website component provided by user input.
     */
    public function process(WebsiteComponent $website): WebsiteComponent
    {
        $url = trim($website->getUrl());

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid website URL: {$url}");
        }

        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            throw new \InvalidArgumentException("Website URL must contain a valid host.");
        }

        // normalize host, remove www/m, lowercase
        $host = strtolower($parsed['host']);
        $host = preg_replace('/^(www\.|m\.)/i', '', $host);

        // assign icon
        if ($this->isUluleHost($host)) {
            $website->setIcon('ulule');
        } elseif (in_array($host, self::KNOWN_LOGOS, true)) {
            $website->setIcon($this->extractBaseIconName($host));
        } else {
            $website->setIcon($this->deterministicFallback($host));
        }

        // cleaned URL
        $website->setUrl($url);
        $website->setUpdatedAt(new \DateTimeImmutable());

        return $website;
    }

    private function extractBaseIconName(string $host): string
    {
        if ($this->isUluleHost($host)) {
            return 'ulule';
        }

        return preg_replace(
            '/\.(com|info|net|io|us|gg|org|me|co\.uk|ca|mobi|gouv\.fr)$/i',
            '',
            $host
        );
    }

    private function isUluleHost(string $host): bool
    {
        return $host === 'ulule.com'
            || $host === 'ulule.fr'
            || str_ends_with($host, '.ulule.com')
            || str_ends_with($host, '.ulule.fr');
    }

    private function deterministicFallback(string $host): string
    {
        $i = crc32($host) % count(self::FALLBACK_ICONS);
        return self::FALLBACK_ICONS[$i];
    }
}
