<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PlaceControllerTest extends WebTestCase
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

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-new-place', $project->getStringId()),
            [
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'type' => 'city',
                'name' => 'Paris',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-new-place', $project->getStringId()),
            [
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'type' => 'city',
                'name' => 'Paris',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
    }
}
