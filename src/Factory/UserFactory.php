<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */

    public function __construct(private ValidatorInterface $validator)
    {
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'isActive' => self::faker()->boolean(),
            'isVerified' => self::faker()->boolean(),
            'roles' => [],
            'username' => self::faker()->text(40),
            'usernameSlug' => self::faker()->text(120),
            'password' => 'test', 
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
        {
            return $this->afterInstantiate(function (User $user): void {
                $errors = $this->validator->validate($user);

                if (\count($errors) > 0) {
                    // this makes doctrine:fixtures:load crash immediately
                    throw new ValidationFailedException($user, $errors);
                }
            });
        }


}
