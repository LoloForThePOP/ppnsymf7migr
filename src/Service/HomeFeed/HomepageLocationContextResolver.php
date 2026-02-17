<?php

namespace App\Service\HomeFeed;

use Symfony\Component\HttpFoundation\Request;

final class HomepageLocationContextResolver
{
    /**
     * @return array{
     *     hint: array{lat: float, lng: float, radius: float}|null,
     *     summary: array{inline: string, info: string}|null
     * }
     */
    public function resolve(Request $request): array
    {
        $hint = $this->extractLocationHint($request);

        return [
            'hint' => $hint,
            'summary' => $this->buildSummary($hint, $request),
        ];
    }

    /**
     * @return array{lat: float, lng: float, radius: float}|null
     */
    private function extractLocationHint(Request $request): ?array
    {
        $raw = trim((string) $request->cookies->get('search_pref_location', ''));
        if ($raw === '') {
            return null;
        }

        $decoded = rawurldecode($raw);
        $parts = preg_split('/[|,]+/', $decoded) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        $lat = filter_var($parts[0], FILTER_VALIDATE_FLOAT);
        $lng = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
        $radius = count($parts) >= 3
            ? filter_var($parts[2], FILTER_VALIDATE_FLOAT)
            : 10.0;

        if ($lat === false || $lng === false) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        if ($radius === false) {
            $radius = 10.0;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => max(1.0, min(200.0, (float) $radius)),
        ];
    }

    /**
     * @param array{lat: float, lng: float, radius: float}|null $locationHint
     * @return array{inline: string, info: string}|null
     */
    private function buildSummary(?array $locationHint, Request $request): ?array
    {
        if ($locationHint === null) {
            return null;
        }

        $rawLabel = trim((string) $request->cookies->get('search_pref_location_label', ''));
        $decodedLabel = $rawLabel !== '' && str_contains($rawLabel, '%')
            ? rawurldecode($rawLabel)
            : $rawLabel;
        $cleanLabel = trim(preg_replace('/\s+/', ' ', strip_tags($decodedLabel)) ?? '');

        if ($cleanLabel !== '') {
            $locationText = strlen($cleanLabel) > 90 ? substr($cleanLabel, 0, 90) . '...' : $cleanLabel;
        } else {
            $locationText = sprintf('%.3f, %.3f', $locationHint['lat'], $locationHint['lng']);
        }

        $radius = max(1, (int) round($locationHint['radius']));

        return [
            'inline' => sprintf('%s · %d km', $locationText, $radius),
            'info' => sprintf(
                'Basé sur votre localisation récente (%s, rayon %d km). La précision peut varier selon le navigateur, GPS, Wi-Fi/IP ou VPN. Si besoin, cliquez sur Modifier et choisissez manuellement votre ville.',
                $locationText,
                $radius
            ),
        ];
    }
}

