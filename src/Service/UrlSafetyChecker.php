<?php

namespace App\Service;

final class UrlSafetyChecker
{
    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const BLOCKED_HOSTS = ['localhost'];
    private const BLOCKED_HOST_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.intranet',
        '.lan',
        '.home',
        '.private',
    ];

    public function isAllowed(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '') {
            return false;
        }

        $host = rtrim($host, '.');

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return false;
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return $this->isHostPublic($host);
    }

    private function isHostPublic(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            // Some shared-hosting environments disable DNS resolution in PHP.
            // In that case we still allow normal domain names (already filtered above)
            // while keeping strict blocking for local/private suffixes and literal IPs.
            return true;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip']) && is_string($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            $resolved = gethostbyname($host);
            if (is_string($resolved) && $resolved !== $host) {
                $ips[] = $resolved;
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }
}
