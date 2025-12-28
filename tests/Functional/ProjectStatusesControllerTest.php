<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectStatusesControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testMissingCsrfIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);

        $client->loginUser($creator);

        $payload = json_encode([
            'statuses' => ['idea'],
            'remarks' => 'Test remarks',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            sprintf('/project/%s/statuses/update', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        self::assertResponseStatusCodeSame(403);
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $response['error'] ?? null);
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $payload = json_encode([
            'statuses' => ['idea'],
            'remarks' => 'Test remarks',
            '_token' => 'dummy',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            sprintf('/project/%s/statuses/update', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        self::assertResponseStatusCodeSame(403);
    }
}
