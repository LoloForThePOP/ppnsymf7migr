<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminAccessTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testNonAdminIsForbiddenFromDashboard(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboard(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testNonAdminIsForbiddenFromHarvestScreen(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/admin/harvest');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminIsForbiddenFromHarvestScreen(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/harvest');

        self::assertResponseStatusCodeSame(403);
    }

    public function testScraperCanAccessHarvestScreen(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $scraper = $this->createUser($em, ['ROLE_SCRAPER']);
        $client->loginUser($scraper);

        $client->request('GET', '/admin/harvest');

        self::assertResponseIsSuccessful();
    }

    public function testNonAdminIsForbiddenFromHarvestRun(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('POST', '/admin/harvest/run');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminIsForbiddenFromProjectNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/admin/project/normalize');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminIsForbiddenFromProjectNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/project/normalize');

        self::assertResponseStatusCodeSame(403);
    }

    public function testScraperCanAccessProjectNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $scraper = $this->createUser($em, ['ROLE_SCRAPER']);
        $client->loginUser($scraper);

        $client->request('GET', '/admin/project/normalize');

        self::assertResponseIsSuccessful();
    }

    public function testNonAdminIsForbiddenFromWebpageNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/admin/project/normalize-html');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminIsForbiddenFromWebpageNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/project/normalize-html');

        self::assertResponseStatusCodeSame(403);
    }

    public function testScraperCanAccessWebpageNormalize(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $scraper = $this->createUser($em, ['ROLE_SCRAPER']);
        $client->loginUser($scraper);

        $client->request('GET', '/admin/project/normalize-html');

        self::assertResponseIsSuccessful();
    }

    public function testNonAdminIsForbiddenFromUrlHarvest(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/admin/project/harvest-urls');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminIsForbiddenFromUrlHarvest(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/project/harvest-urls');

        self::assertResponseStatusCodeSame(403);
    }

    public function testScraperCanAccessUrlHarvest(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $scraper = $this->createUser($em, ['ROLE_SCRAPER']);
        $client->loginUser($scraper);

        $client->request('GET', '/admin/project/harvest-urls');

        self::assertResponseIsSuccessful();
    }
}
