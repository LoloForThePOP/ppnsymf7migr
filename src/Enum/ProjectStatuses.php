<?php

namespace App\Model;

final class ProjectStatuses
{
    /**
     * The complete taxonomy of project statuses.
     * Each category has labels and a list of status items.
     */
    public const CATALOG = [

        // ─────────────────────────────────────────────
        // GENERAL PROGRESS
        // ─────────────────────────────────────────────
        [
            'categoryLabels' => [
                'uniqueName' => 'general',
                'description_fr' => 'Votre projet est',
            ],
            'items' => [
                [
                    'uniqueName' => 'idea',
                    'description_fr' => "Encore à l'état d'idée ou de réflexion / prévu",
                    'short_description_fr' => "Idée / prévu",
                    'bg_color' => '#95d2ff4a',
                ],
                [
                    'uniqueName' => 'production',
                    'description_fr' => "La réalisation concrète est démarrée",
                    'short_description_fr' => "En cours",
                    'bg_color' => '#e7fbbb',
                ],
                [
                    'uniqueName' => 'pause',
                    'description_fr' => "En pause",
                    'short_description_fr' => "En pause",
                    'bg_color' => '#fffd8c',
                ],
                [
                    'uniqueName' => 'cancel',
                    'description_fr' => "Annulé ou abandonné",
                    'short_description_fr' => "Annulé / abandonné",
                    'bg_color' => '#f9dad2e0',
                ],
                [
                    'uniqueName' => 'done',
                    'description_fr' => "Terminé",
                    'short_description_fr' => "Terminé ✓",
                    'bg_color' => '#e2ffb7',
                ],
            ],
        ],

        // ─────────────────────────────────────────────
        // SALES OR COMMERCIAL STATUS
        // ─────────────────────────────────────────────
        [
            'categoryLabels' => [
                'uniqueName' => 'sales',
                'description_fr' => "S'il y a ou aura des ventes",
            ],
            'items' => [
                [
                    'uniqueName' => 'will_be_marketed',
                    'description_fr' => "Le produit ou service n'est pas encore commercialisé",
                    'short_description_fr' => "Sera commercialisé",
                    'bg_color' => '#ffb4717d',
                ],
                [
                    'uniqueName' => 'product_for_sale',
                    'description_fr' => "Le produit ou service commence à être commercialisé",
                    'short_description_fr' => "Commercialisé",
                    'bg_color' => '#ffb4717d',
                ],
                [
                    'uniqueName' => 'first_sales_done',
                    'description_fr' => "Des premières ventes sont effectuées",
                    'short_description_fr' => "Premières ventes effectuées",
                    'bg_color' => '#ffb4717d',
                ],
                [
                    'uniqueName' => 'marketed_significantly',
                    'description_fr' => "Déjà commercialisé à moyenne ou grande échelle",
                    'short_description_fr' => "Commercialisé à grande échelle",
                    'bg_color' => '#ffb4717d',
                ],
            ],
        ],

        // ─────────────────────────────────────────────
        // MODELISATION / PROTOTYPING
        // ─────────────────────────────────────────────
        [
            'categoryLabels' => [
                'uniqueName' => 'modelisation',
                'description_fr' => "Si vous créez un objet matériel",
            ],
            'items' => [
                [
                    'uniqueName' => 'computer_simulation',
                    'description_fr' => "C'est actuellement une simulation informatique",
                    'short_description_fr' => "Simulation informatique",
                    'bg_color' => '#d18eff4a',
                ],
                [
                    'uniqueName' => 'labo_prototype',
                    'description_fr' => "C'est actuellement un prototype testé en laboratoire",
                    'short_description_fr' => "Prototype en labo",
                    'bg_color' => '#d18eff4a',
                ],
                [
                    'uniqueName' => 'real_world_prototype',
                    'description_fr' => "C'est actuellement un prototype testé en conditions réelles",
                    'short_description_fr' => "Prototype en conditions réelles",
                    'bg_color' => '#d18eff4a',
                ],
                [
                    'uniqueName' => 'realised_object',
                    'description_fr' => "L'objet est réalisé",
                    'short_description_fr' => "Objet réalisé",
                    'bg_color' => '#d18eff4a',
                ],
            ],
        ],

        // ─────────────────────────────────────────────
        // SUBMISSION / DECISION
        // ─────────────────────────────────────────────
        [
            'categoryLabels' => [
                'uniqueName' => 'submission',
                'description_fr' => "Si le projet est soumis à une décision ou un vote",
            ],
            'items' => [
                [
                    'uniqueName' => 'submitted',
                    'description_fr' => "Le projet est proposé (décision en attente)",
                    'short_description_fr' => "Décision en attente",
                    'bg_color' => '#ffc0cb',
                ],
                [
                    'uniqueName' => 'approved',
                    'description_fr' => "Décision acceptée",
                    'short_description_fr' => "Décision acceptée",
                    'bg_color' => '#ffc0cb',
                ],
                [
                    'uniqueName' => 'rejected',
                    'description_fr' => "Décision rejetée",
                    'short_description_fr' => "Décision rejetée",
                    'bg_color' => '#ffc0cb',
                ],
                [
                    'uniqueName' => 'postponed',
                    'description_fr' => "Décision reportée à une date indéterminée",
                    'short_description_fr' => "Décision reportée",
                    'bg_color' => '#ffc0cb',
                ],
            ],
        ],
    ];

    // ─────────────────────────────────────────────
    // BASIC UTILITIES
    // ─────────────────────────────────────────────

    public static function all(): array
    {
        return self::CATALOG;
    }

    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::CATALOG as $cat) {
            foreach ($cat['items'] as $item) {
                $keys[] = $item['uniqueName'];
            }
        }
        return $keys;
    }

    public static function get(string $key): ?array
    {
        foreach (self::CATALOG as $cat) {
            foreach ($cat['items'] as $item) {
                if ($item['uniqueName'] === $key) {
                    return $item;
                }
            }
        }
        return null;
    }

    public static function findCategoryOf(string $status): ?string
    {
        foreach (self::CATALOG as $cat) {
            foreach ($cat['items'] as $item) {
                if ($item['uniqueName'] === $status) {
                    return $cat['categoryLabels']['uniqueName'];
                }
            }
        }
        return null;
    }

    public static function getCategoryLabel(string $categoryKey, string $locale = 'fr'): ?string
    {
        foreach (self::CATALOG as $cat) {
            if ($cat['categoryLabels']['uniqueName'] === $categoryKey) {
                return $cat['categoryLabels']["description_{$locale}"] ?? null;
            }
        }
        return null;
    }

    public static function getCategoryItems(string $categoryKey): array
    {
        foreach (self::CATALOG as $cat) {
            if ($cat['categoryLabels']['uniqueName'] === $categoryKey) {
                return $cat['items'];
            }
        }
        return [];
    }

    // ─────────────────────────────────────────────
    // FORMS
    // ─────────────────────────────────────────────

    /**
     * Returns grouped choices for Symfony forms.
     */
    public static function choicesForForm(string $locale = 'fr'): array
    {
        $choices = [];
        foreach (self::CATALOG as $cat) {
            $groupLabel = $cat['categoryLabels']["description_{$locale}"] ?? $cat['categoryLabels']['uniqueName'];
            $choices[$groupLabel] = [];
            foreach ($cat['items'] as $item) {
                $choices[$groupLabel][$item["short_description_{$locale}"] ?? $item['uniqueName']] = $item['uniqueName'];
            }
        }
        return $choices;
    }
}
