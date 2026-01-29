<?php

namespace App\Service;

use App\Entity\Profile;

/**
 * Manage arbitrary structured data stored in UserProfile::$extra (JSON column).
 *
 * Features:
 *  - Default values for known keys
 *  - Type validation
 *  - Automatic initialization on first access
 */
class UserExtraService
{
    /**
     * Default values for recognized keys.
     * @var array<string, mixed>
     */
    private array $defaults = [
        'theme'             => 'light',          // string
        'language'          => 'fr',             // string (ISO code)
        'newsletter'        => false,            // bool
        'timezone'          => 'Europe/Paris',   // string
        'dashboard_layout'  => 'default',        // string
        'last_seen_at'      => null,             // \DateTimeInterface|null
        'product_tours'     => [],               // array<string, string>
        'product_tour_visits' => [],             // array<string, int>
        'search_history'    => [],               // array<int, string>
    ];

    /**
     * Expected types for validation.
     * @var array<string, string>
     */
    private array $types = [
        'theme'             => 'string',
        'language'          => 'string',
        'newsletter'        => 'bool',
        'timezone'          => 'string',
        'dashboard_layout'  => 'string',
        'last_seen_at'      => 'datetime',
        'product_tours'     => 'array',
        'product_tour_visits' => 'array',
        'search_history'    => 'array',
    ];

    // -------------------------------------------------------------

    /**
     * Get all extras merged with defaults. Auto-initializes if empty.
     */
    public function all(Profile $profile): array
    {
        $extra = $this->initializeIfNeeded($profile);
        return array_merge($this->defaults, $extra);
    }

    /**
     * Get a specific key (returns default if not set).
     */
    public function get(Profile $profile, string $key, mixed $fallback = null): mixed
    {
        $extra = $this->initializeIfNeeded($profile);
        return $extra[$key] ?? $this->defaults[$key] ?? $fallback;
    }

    /**
     * Set a specific key, validating its type.
     */
    public function set(Profile $profile, string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->defaults)) {
            throw new \InvalidArgumentException("Unknown extra key '$key'.");
        }

        $this->assertType($key, $value);

        $extra = $this->initializeIfNeeded($profile);
        $extra[$key] = $this->normalizeValue($key, $value);
        $profile->setExtra($extra);
    }

    /**
     * Reset to defaults.
     */
    public function reset(Profile $profile): void
    {
        $profile->setExtra($this->defaults);
    }

    /**
     * Get all defined keys and their types.
     */
    public function describe(): array
    {
        $desc = [];
        foreach ($this->defaults as $k => $v) {
            $desc[$k] = [
                'type' => $this->types[$k] ?? 'mixed',
                'default' => $v,
            ];
        }
        return $desc;
    }

    // -------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------

    private function initializeIfNeeded(Profile $profile): array
    {
        $extra = $profile->getExtra() ?? [];

        $changed = false;
        foreach ($this->defaults as $key => $value) {
            if (!array_key_exists($key, $extra)) {
                $extra[$key] = $value;
                $changed = true;
            }
        }
        if ($changed) {
            $profile->setExtra($extra);
        }
        return $extra;
    }

    private function assertType(string $key, mixed $value): void
    {
        $expected = $this->types[$key] ?? null;
        if (!$expected) {
            return;
        }

        $ok = match ($expected) {
            'string'   => is_string($value),
            'bool'     => is_bool($value),
            'int'      => is_int($value),
            'float'    => is_float($value),
            'datetime' => $value === null || $value instanceof \DateTimeInterface,
            'array'    => is_array($value),
            default    => true,
        };

        if (!$ok) {
            $given = get_debug_type($value);
            throw new \InvalidArgumentException("Invalid type for '$key': expected $expected, got $given");
        }
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if (($this->types[$key] ?? null) === 'datetime') {
            if ($value instanceof \DateTimeInterface) {
                return $value->format(\DateTimeInterface::ATOM);
            }
            if (is_string($value)) {
                // attempt to parse back to standardized format
                $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)
                    ?: new \DateTimeImmutable($value);
                return $dt->format(\DateTimeInterface::ATOM);
            }
        }
        return $value;
    }
}
