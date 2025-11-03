<?php

namespace App\DataFixtures;

use App\Factory\UserFactory;
use App\Factory\PPBaseFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1️⃣ Create a base set of users
        UserFactory::createMany(50);

        // 2️⃣ Create 120 project presentations
        // Randomly assign creators among existing users
        PPBaseFactory::createMany(120);

        // 3️⃣ Optionally: create a few special admin users
        UserFactory::new([
            'email' => 'admin@example.com',
            'username' => 'AdminUser',
            'roles' => ['ROLE_ADMIN'],
        ])->create();

        // 4️⃣ Flush everything
        $manager->flush();
    }
}
