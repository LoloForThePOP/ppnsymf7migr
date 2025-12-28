<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LikeControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testMissingCsrfIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $liker = $this->createUser($em);

        $client->loginUser($liker);

        $client->request('POST', sprintf('/project/%s/like', $project->getStringId()));

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Invalid CSRF token', $payload['error'] ?? null);
    }

    public function testLikeToggleCreatesAndRemoves(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $liker = $this->createUser($em);

        $client->loginUser($liker);

        $tokenId = 'like' . $project->getStringId();
        $token = $this->getCsrfToken($client, $tokenId);

        $client->request('POST', sprintf('/project/%s/like', $project->getStringId()), [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('created', $payload['action'] ?? null);

        $client->request('POST', sprintf('/project/%s/like', $project->getStringId()), [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('removed', $payload['action'] ?? null);
    }
}
