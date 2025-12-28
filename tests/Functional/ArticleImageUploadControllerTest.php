<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ArticleImageUploadControllerTest extends WebTestCase
{
    use ResetDatabase;

    private function createUser(EntityManagerInterface $em): User
    {
        $user = (new User())
            ->setEmail(sprintf('uploader+%s@example.com', uniqid('', true)))
            ->setUsername('uploader')
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

        $client->request('POST', '/articles/upload-image');

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('CSRF Denied', $payload['message'] ?? null);
    }

    public function testMissingFileWithValidCsrfReturnsBadRequest(): void
    {
        $client = $this->createClientWithUser();

        $token = $this->getCsrfToken($client, 'tinymce_image_upload');

        $client->request('POST', '/articles/upload-image', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Aucun fichier', $payload['error'] ?? null);
    }
}
