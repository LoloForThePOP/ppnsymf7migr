<?php

namespace App\Tests\Functional;

use App\Entity\PresentationEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PresentationEventControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testUnpublishedProjectRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $project->setIsPublished(false);
        $em->flush();

        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'share_open'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseRedirects('/login');
    }

    public function testOwnerCanLogEventOnUnpublishedProject(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $project->setIsPublished(false);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'share_open'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'] ?? false);

        $event = $em->getRepository(PresentationEvent::class)->findOneBy([
            'projectPresentation' => $project,
            'type' => PresentationEvent::TYPE_SHARE_OPEN,
        ]);
        self::assertNotNull($event);
        self::assertSame([], $event->getMeta() ?? []);
    }

    public function testRejectsMalformedJsonPayload(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"type":'
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid_payload', $payload['error'] ?? null);
    }

    public function testRejectsInvalidType(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'view'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid_type', $payload['error'] ?? null);
    }

    public function testRejectsInvalidMetaForShareExternal(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'type' => 'share_external',
                'meta' => ['channel' => 'my_custom_channel'],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid_meta', $payload['error'] ?? null);
    }

    public function testAcceptsShareExternalAndNormalizesChannel(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/pp/%s/event', $project->getStringId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'type' => 'share_external',
                'meta' => ['channel' => '  LinkedIn '],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'] ?? false);

        $event = $em->getRepository(PresentationEvent::class)->findOneBy([
            'projectPresentation' => $project,
            'type' => PresentationEvent::TYPE_SHARE_EXTERNAL,
        ]);
        self::assertNotNull($event);
        self::assertSame(['channel' => 'linkedin'], $event->getMeta());
    }

    public function testPresentationEventRateLimiterReturnsTooManyRequests(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $client->loginUser($owner);

        $payload = json_encode(['type' => 'share_open'], JSON_THROW_ON_ERROR);
        $headers = ['CONTENT_TYPE' => 'application/json'];

        $client->request('POST', sprintf('/pp/%s/event', $project->getStringId()), [], [], $headers, $payload);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/pp/%s/event', $project->getStringId()), [], [], $headers, $payload);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/pp/%s/event', $project->getStringId()), [], [], $headers, $payload);
        self::assertResponseStatusCodeSame(429);
    }
}
