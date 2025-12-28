<?php

namespace App\Tests\Functional;

use App\Entity\PPBase;
use App\Entity\User;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LiveSaveControllerTest extends WebTestCase
{
    use ResetDatabase;

    private function createUser(EntityManagerInterface $em): User
    {
        $user = (new User())
            ->setEmail(sprintf('editor+%s@example.com', uniqid('', true)))
            ->setUsername(sprintf('editor_%s', uniqid('', true)))
            ->setPassword('dummy')
            ->setIsActive(true)
            ->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createClientWithUser(): KernelBrowser
    {
        $client = static::createClient();

        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($this->createUser($em));

        return $client;
    }

    private function createProject(EntityManagerInterface $em, User $creator): PPBase
    {
        $project = (new PPBase())
            ->setGoal('Test goal for live save.')
            ->setCreator($creator);

        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function getCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $container = $client->getContainer();
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();

        $cookie = $client->getCookieJar()->get($session->getName());
        if ($cookie !== null) {
            $session->setId($cookie->getValue());
        }

        $session->start();

        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $token = $container->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
            $session->save();
        } finally {
            $requestStack->pop();
        }

        return $token;
    }

    public function testMissingCsrfIsRejected(): void
    {
        $client = $this->createClientWithUser();

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('CSRF', (string) ($payload['error'] ?? ''));
    }

    public function testMissingMetadataIsRejected(): void
    {
        $client = $this->createClientWithUser();

        $token = $this->getCsrfToken($client, 'live_save_pp');

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            ['_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('manquantes', (string) ($payload['error'] ?? ''));
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $token = $this->getCsrfToken($client, 'live_save_pp');
        $metadata = json_encode([
            'entity' => 'ppbase',
            'id' => $presentation->getId(),
            'property' => 'title',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            [
                '_token' => $token,
                'metadata' => $metadata,
                'content' => 'Unauthorized update',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testLiveSaveUpdatesTitle(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'live_save_pp');
        $metadata = json_encode([
            'entity' => 'ppbase',
            'id' => $presentation->getId(),
            'property' => 'title',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            [
                '_token' => $token,
                'metadata' => $metadata,
                'content' => 'Updated title',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $em->clear();
        $presentation = $em->getRepository(PPBase::class)->find($presentation->getId());
        self::assertSame('Updated title', $presentation?->getTitle());
    }

    public function testLiveSaveUpdatesWebsiteUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = WebsiteComponent::createNew('Example', 'https://example.test');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('websites', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'live_save_pp');
        $metadata = json_encode([
            'entity' => 'ppbase',
            'id' => $presentation->getId(),
            'property' => 'websites',
            'subid' => $component->getId(),
            'subproperty' => 'url',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            [
                '_token' => $token,
                'metadata' => $metadata,
                'content' => 'https://example.org',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $em->clear();
        $presentation = $em->getRepository(PPBase::class)->find($presentation->getId());
        $websites = $presentation?->getOtherComponents()->getComponents('websites') ?? [];
        $updated = null;
        foreach ($websites as $website) {
            if ($website->getId() === $component->getId()) {
                $updated = $website;
                break;
            }
        }

        self::assertSame('https://example.org', $updated?->getUrl());
    }

    public function testLiveSaveRejectsInvalidWebsiteUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = WebsiteComponent::createNew('Example', 'https://example.test');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('websites', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'live_save_pp');
        $metadata = json_encode([
            'entity' => 'ppbase',
            'id' => $presentation->getId(),
            'property' => 'websites',
            'subid' => $component->getId(),
            'subproperty' => 'url',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/project/ajax-inline-save',
            [
                '_token' => $token,
                'metadata' => $metadata,
                'content' => 'javascript:alert(1)',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('adresse web valide', (string) ($payload['error'] ?? ''));

        $em->clear();
        $presentation = $em->getRepository(PPBase::class)->find($presentation->getId());
        $websites = $presentation?->getOtherComponents()->getComponents('websites') ?? [];
        $unchanged = null;
        foreach ($websites as $website) {
            if ($website->getId() === $component->getId()) {
                $unchanged = $website;
                break;
            }
        }

        self::assertSame('https://example.test', $unchanged?->getUrl());
    }
}
