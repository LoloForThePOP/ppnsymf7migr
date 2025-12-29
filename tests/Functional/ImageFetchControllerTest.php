<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ImageFetchControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testNonAdminIsForbidden(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('GET', sprintf('/project/%s/images', $project->getStringId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessImageFetch(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $project = $this->createProject($em, $admin);

        $client->loginUser($admin);
        $client->request('GET', sprintf('/project/%s/images', $project->getStringId()));

        self::assertResponseIsSuccessful();
    }

    public function testMissingCsrfOnPostIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $project = $this->createProject($em, $admin);

        $client->loginUser($admin);
        $client->request(
            'POST',
            sprintf('/project/%s/images', $project->getStringId()),
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }
}
