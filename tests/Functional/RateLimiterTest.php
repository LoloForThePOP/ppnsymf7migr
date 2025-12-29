<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RateLimiterTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testFollowRateLimiterReturnsTooManyRequests(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $follower = $this->createUser($em);

        $client->loginUser($follower);

        $tokenId = 'follow' . $project->getStringId();
        $token = $this->getCsrfToken($client, $tokenId);

        $client->request('POST', sprintf('/project/%s/follow', $project->getStringId()), [
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/project/%s/follow', $project->getStringId()), [
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/project/%s/follow', $project->getStringId()), [
            '_token' => $token,
        ]);
        self::assertResponseStatusCodeSame(429);
    }
}
