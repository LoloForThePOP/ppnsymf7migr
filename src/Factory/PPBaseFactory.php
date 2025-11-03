<?php

namespace App\Factory;

use App\Entity\PPBase;
use App\Factory\Embeddables\PPBase\ExtraFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PPBase>
 */
final class PPBaseFactory extends PersistentProxyObjectFactory
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
        return PPBase::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            // ✅ randomly reuse existing user, or create a new one if none exist
            'creator' => UserFactory::randomOrCreate(),

            'extra' => ExtraFactory::new(),
            'goal' => self::faker()->text(400),
            'isAdminValidated' => self::faker()->boolean(),
            'isPublished' => self::faker()->boolean(),
            'stringId' => self::faker()->slug(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(PPBase $pPBase): void {})
        ;
    }
}
