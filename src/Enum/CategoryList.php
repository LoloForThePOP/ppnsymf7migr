<?php

namespace App\Enum;

enum CategoryList: string
{

    case SOFTWARE = 'software';
    case SCIENCE = 'science';
    case INFORM = 'inform';
    case HUMANE = 'humane'; 
    case ANIMALS = 'animals';
    case MATERIAL = 'material';
    case RESTORE = 'restore';
    case TRANSPORT = 'transport';
    case ENVIRONMENT = 'environment';
    case HISTORY = 'history';
    case MONEY = 'money';
    case FOOD = 'food';
    case SERVICES = 'services';
    case ARTS = 'arts';    
    case ENTERTAINMENT = 'entertainment';
    case DATA = 'data';     
    case HEALTH = 'health';
    case IDEA = 'idea';
    case SPACE = 'space';
    case CRISIS = 'crisis';



    /**
     * Human-readable label for each category.
     */

    public function label(): string
    {
        return match ($this) {
            self::SOFTWARE      => 'Informatique, Codage, Internet',
            self::SCIENCE       => 'Science, Recherche',
            self::INFORM        => 'Informer, Éduquer, Apprendre',
            self::HUMANE        => 'Vivre Ensemble, Humanitaire',
            self::ANIMALS       => 'Animaux',
            self::MATERIAL      => 'Fabriquer, Construire',
            self::RESTORE       => 'Restaurer, Rénover, Recycler',
            self::TRANSPORT     => 'Transporter',
            self::ENVIRONMENT   => 'Environnement',
            self::HISTORY       => 'Histoire, Patrimoine',
            self::MONEY         => 'Finance, Argent',
            self::FOOD          => 'Agriculture, Nourriture',
            self::SERVICES      => 'Services, Mise en relation',
            self::ARTS          => 'Culture, Arts',
            self::ENTERTAINMENT => 'Divertissements, Loisirs, Sports',
            self::DATA          => 'Organiser des données',
            self::HEALTH        => 'Santé',
            self::IDEA          => 'Idées, Politique',
            self::SPACE         => 'Air et Espace',
            self::CRISIS        => 'Crise',
        };
    }

    
    /**
    * Derive icon filename from unique name.
    */

    public function icon(): string
    {
        // consistent naming convention: public/category_icons/{uniqueName}.svg
        return sprintf('%s.svg', $this->value);
    }



    /**
     * Dynamically derive position based on declaration order
     */
    public function position(): int
    {
        static $order = null;

        if ($order === null) {
            $order = array_map(
                static fn($case) => $case->value,
                self::cases()
            );
        }

        return array_search($this->value, $order, true) + 1;
    }









}





