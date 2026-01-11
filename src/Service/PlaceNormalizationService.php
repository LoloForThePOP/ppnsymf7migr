<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlaceNormalizationService
{
    private const COMPONENT_KEYS = [
        'street_number',
        'route',
        'sublocality_level_1',
        'postal_code',
        'locality',
        'administrative_area_level_2',
        'administrative_area_level_1',
        'country',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GOOGLE_MAPS_SERVER_API_KEY')]
        private readonly string $googleMapsServerApiKey,
    ) {
    }

    /**
     * @param array<int, mixed> $places
     * @return array<int, array<string, mixed>>
     */
    public function normalizePlaces(array $places, int $limit = 3, ?string $defaultCountry = null): array
    {
        $result = $this->normalizePlacesWithDebug($places, $limit, $defaultCountry);

        return $result['places'];
    }

    /**
     * @param array<int, mixed> $places
     * @return array{places: array<int, array<string, mixed>>, debug: array<int, array<string, mixed>>}
     */
    public function normalizePlacesWithDebug(array $places, int $limit = 3, ?string $defaultCountry = null): array
    {
        $normalized = [];
        $debug = [];
        $seen = [];

        foreach ($places as $entry) {
            $result = $this->normalizePlaceWithDebug($entry, $defaultCountry);
            $place = $result['place'];
            $debugEntry = $result['debug'];

            if ($place !== null) {
                $fingerprint = $this->fingerprint($place);
                if (isset($seen[$fingerprint])) {
                    $debugEntry['status'] = 'duplicate';
                    $debugEntry['message'] = 'Duplicate place (filtered).';
                    $debugEntry['resolved'] = $this->resolvedSummary($place);
                } else {
                    $seen[$fingerprint] = true;
                    $normalized[] = $place;
                }
            }

            $debug[] = $debugEntry;

            if (count($normalized) >= $limit) {
                break;
            }
        }

        return ['places' => $normalized, 'debug' => $debug];
    }

    private function normalizePlace(mixed $entry, ?string $defaultCountry = null): ?array
    {
        $result = $this->normalizePlaceWithDebug($entry, $defaultCountry);

        return $result['place'];
    }

    /**
     * @return array{place: ?array<string, mixed>, debug: array<string, mixed>}
     */
    private function normalizePlaceWithDebug(mixed $entry, ?string $defaultCountry = null): array
    {
        $query = null;
        $type = null;
        $place = [];

        if (is_string($entry)) {
            $query = trim($entry);
            if ($query === '') {
                return ['place' => null, 'debug' => $this->buildDebugEntry(null, 'invalid_input', 'Empty query.')];
            }
            return $this->geocodeQueryWithDebug($query, null, $defaultCountry);
        } elseif (is_array($entry)) {
            $query = $this->stringValue($entry['query'] ?? $entry['name'] ?? $entry['label'] ?? null);
            $type = $this->stringValue($entry['type'] ?? null);

            $place = $this->normalizeDirectPlace($entry, $type);
            if ($place !== null) {
                return [
                    'place' => $place,
                    'debug' => $this->buildDebugEntry($query ?? $place['name'] ?? null, 'direct', null, $place),
                ];
            }

            if ($query === null) {
                $query = $this->buildQueryFromComponents($entry);
            }
        } else {
            return ['place' => null, 'debug' => $this->buildDebugEntry(null, 'invalid_input', 'Unsupported entry.')];
        }

        if ($query === null || $query === '') {
            return ['place' => null, 'debug' => $this->buildDebugEntry(null, 'invalid_input', 'Missing query.')];
        }

        return $this->geocodeQueryWithDebug($query, $type, $defaultCountry);
    }

    private function normalizeDirectPlace(array $entry, ?string $type): ?array
    {
        $lat = $this->floatValue($entry['lat'] ?? $entry['latitude'] ?? null);
        $lng = $this->floatValue($entry['lng'] ?? $entry['longitude'] ?? null);

        if ($lat === null || $lng === null) {
            return null;
        }

        $name = $this->stringValue($entry['name'] ?? $entry['label'] ?? $entry['query'] ?? null);
        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'type' => $type ?? 'generic',
            'country' => $this->stringValue($entry['country'] ?? null),
            'administrative_area_level_1' => $this->stringValue($entry['administrative_area_level_1'] ?? $entry['administrativeAreaLevel1'] ?? null),
            'administrative_area_level_2' => $this->stringValue($entry['administrative_area_level_2'] ?? $entry['administrativeAreaLevel2'] ?? null),
            'locality' => $this->stringValue($entry['locality'] ?? null),
            'sublocality_level_1' => $this->stringValue($entry['sublocality_level_1'] ?? $entry['sublocalityLevel1'] ?? null),
            'postal_code' => $this->stringValue($entry['postal_code'] ?? $entry['postalCode'] ?? null),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function buildQueryFromComponents(array $entry): ?string
    {
        $parts = [];
        foreach (['locality', 'administrative_area_level_2', 'administrative_area_level_1', 'country'] as $key) {
            $value = $this->stringValue($entry[$key] ?? null);
            if ($value) {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function geocodeQuery(string $query, ?string $type, ?string $defaultCountry): ?array
    {
        $result = $this->geocodeQueryWithDebug($query, $type, $defaultCountry);

        return $result['place'];
    }

    /**
     * @return array{place: ?array<string, mixed>, debug: array<string, mixed>}
     */
    private function geocodeQueryWithDebug(string $query, ?string $type, ?string $defaultCountry): array
    {
        if (trim($this->googleMapsServerApiKey) === '') {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'missing_api_key', 'GOOGLE_MAPS_SERVER_API_KEY is empty.'),
            ];
        }

        $params = [
            'address' => $query,
            'key' => $this->googleMapsServerApiKey,
            'language' => 'fr',
        ];

        $country = $this->stringValue($defaultCountry);
        if ($country !== null) {
            $params['region'] = strtolower($country);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => $params,
                'timeout' => 10,
            ]);
        } catch (TransportExceptionInterface) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'transport_error', 'Transport error while calling geocode.'),
            ];
        }

        $data = $response->toArray(false);
        if (!is_array($data)) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'invalid_response', 'Geocode response was not a JSON object.'),
            ];
        }

        $status = $data['status'] ?? null;
        if (!is_string($status)) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'invalid_response', 'Geocode response missing status.'),
            ];
        }

        if ($status !== 'OK') {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry(
                    $query,
                    $status,
                    $this->stringValue($data['error_message'] ?? null)
                ),
            ];
        }

        $results = $data['results'] ?? null;
        if (!is_array($results) || $results === []) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'ZERO_RESULTS', 'No geocode results found.'),
            ];
        }

        $result = $results[0];
        if (!is_array($result)) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'invalid_response', 'Geocode result format is invalid.'),
            ];
        }

        $components = $this->extractComponents($result['address_components'] ?? null);
        $location = is_array($result['geometry'] ?? null) ? ($result['geometry']['location'] ?? null) : null;
        $lat = is_array($location) ? $this->floatValue($location['lat'] ?? null) : null;
        $lng = is_array($location) ? $this->floatValue($location['lng'] ?? null) : null;

        if ($lat === null || $lng === null) {
            return [
                'place' => null,
                'debug' => $this->buildDebugEntry($query, 'missing_coordinates', 'Geocode response missing coordinates.'),
            ];
        }

        $resolvedType = $type ?? $this->guessType($components, $result['types'] ?? null);
        $resolvedName = $this->guessName($resolvedType, $components, $result['formatted_address'] ?? null, $query);

        $place = [
            'name' => $resolvedName,
            'type' => $resolvedType,
            'country' => $components['country'] ?? null,
            'administrative_area_level_1' => $components['administrative_area_level_1'] ?? null,
            'administrative_area_level_2' => $components['administrative_area_level_2'] ?? null,
            'locality' => $components['locality'] ?? null,
            'sublocality_level_1' => $components['sublocality_level_1'] ?? null,
            'postal_code' => $components['postal_code'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
        ];

        return [
            'place' => $place,
            'debug' => $this->buildDebugEntry($query, 'OK', null, $place),
        ];
    }

    private function extractComponents(mixed $components): array
    {
        $mapped = array_fill_keys(self::COMPONENT_KEYS, null);

        if (!is_array($components)) {
            return $mapped;
        }

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $types = $component['types'] ?? null;
            if (!is_array($types)) {
                continue;
            }
            foreach ($types as $type) {
                if (!is_string($type) || !array_key_exists($type, $mapped)) {
                    continue;
                }
                $value = $this->stringValue($component['long_name'] ?? null);
                if ($value !== null) {
                    $mapped[$type] = $value;
                }
            }
        }

        return $mapped;
    }

    private function guessType(array $components, mixed $types): string
    {
        foreach (['locality', 'postal_code', 'administrative_area_level_2', 'administrative_area_level_1', 'country'] as $key) {
            if (!empty($components[$key])) {
                return $key;
            }
        }

        if (!empty($components['route']) || !empty($components['street_number'])) {
            return 'route';
        }

        if (is_array($types)) {
            foreach ($types as $type) {
                if (is_string($type) && $type !== '') {
                    return $type;
                }
            }
        }

        return 'generic';
    }

    private function guessName(string $type, array $components, ?string $formattedAddress, string $query): string
    {
        if (in_array($type, ['street_number', 'route', 'street_address'], true)) {
            $street = trim(sprintf('%s %s', $components['street_number'] ?? '', $components['route'] ?? ''));
            if ($street !== '') {
                return $street;
            }
        }

        if (!empty($components[$type])) {
            return $components[$type];
        }

        foreach (['locality', 'administrative_area_level_2', 'administrative_area_level_1', 'country'] as $key) {
            if (!empty($components[$key])) {
                return $components[$key];
            }
        }

        if ($formattedAddress !== null && trim($formattedAddress) !== '') {
            return trim($formattedAddress);
        }

        return $query;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function floatValue(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function fingerprint(array $place): string
    {
        $lat = $place['lat'] ?? null;
        $lng = $place['lng'] ?? null;
        if (is_float($lat) || is_int($lat)) {
            $lat = round((float) $lat, 6);
        }
        if (is_float($lng) || is_int($lng)) {
            $lng = round((float) $lng, 6);
        }
        if ($lat !== null && $lng !== null) {
            return sprintf('geo:%s,%s', $lat, $lng);
        }

        $name = strtolower(trim((string) ($place['name'] ?? '')));
        $locality = strtolower(trim((string) ($place['locality'] ?? '')));
        $country = strtolower(trim((string) ($place['country'] ?? '')));

        return sprintf('text:%s|%s|%s', $name, $locality, $country);
    }

    /**
     * @param array<string, mixed>|null $place
     * @return array<string, mixed>
     */
    private function buildDebugEntry(?string $query, string $status, ?string $message = null, ?array $place = null): array
    {
        $entry = [
            'query' => $query,
            'status' => $status,
        ];

        if ($message !== null && $message !== '') {
            $entry['message'] = $message;
        }

        if ($place !== null) {
            $entry['resolved'] = $this->resolvedSummary($place);
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $place
     * @return array<string, mixed>
     */
    private function resolvedSummary(array $place): array
    {
        $lat = $place['lat'] ?? null;
        $lng = $place['lng'] ?? null;

        return [
            'name' => $this->stringValue($place['name'] ?? null),
            'type' => $this->stringValue($place['type'] ?? null),
            'lat' => (is_float($lat) || is_int($lat)) ? (float) $lat : null,
            'lng' => (is_float($lng) || is_int($lng)) ? (float) $lng : null,
        ];
    }
}
