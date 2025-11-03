<?php

namespace App\Factory\Embeddables\PPBase;

use App\Entity\Embeddables\PPBase\OtherComponents;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<OtherComponents>
 */
final class OtherComponentsFactory extends ObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return OtherComponents::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(OtherComponents $otherComponents): void {})
        ;
    }
}
