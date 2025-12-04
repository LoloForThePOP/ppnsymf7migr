<?php 

namespace App\Entity\Embeddables\PPBase\OtherComponentsModels;

use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;

class ComponentRegistry
{

    /**
     * Map component types (keys used in JSON) to their PHP classes.
     *
     * @var array<string, class-string<ComponentInterface>>
     */
    private const MAP = [
        'websites' => WebsiteComponent::class,
        'questions_answers' => QuestionAnswerComponent::class,
        'business_cards' => BusinessCardComponent::class,
    ];

    public static function classFor(string $type): ?string
    {
        return self::MAP[$type] ?? null;
    }
}
