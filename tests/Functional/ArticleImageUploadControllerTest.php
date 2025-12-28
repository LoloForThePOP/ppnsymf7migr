<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    private function createTempImagePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==',
            true
        );
        if ($png === false) {
            throw new \RuntimeException('Failed to decode PNG fixture.');
        }

        file_put_contents($path, $png);

        return $path;
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

    public function testValidImageUploadReturnsLocation(): void
    {
        $client = $this->createClientWithUser();

        $token = $this->getCsrfToken($client, 'tinymce_image_upload');
        $tempFile = $this->createTempImagePath();

        $client->request(
            'POST',
            '/articles/upload-image',
            ['_token' => $token],
            ['file' => new UploadedFile($tempFile, 'test.png', 'image/png', null, true)],
            ['HTTP_ORIGIN' => 'http://localhost']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($payload['location'] ?? null);

        $path = parse_url($payload['location'], PHP_URL_PATH);
        self::assertNotEmpty($path);
        self::assertStringContainsString('/media/uploads/articles/images/', (string) $path);

        $filename = basename((string) $path);
        $uploadDir = rtrim((string) $client->getContainer()->getParameter('app.image_upload_directory'), '/');
        $storedFile = $uploadDir.'/'.$filename;

        self::assertFileExists($storedFile);

        if (is_file($storedFile)) {
            unlink($storedFile);
        }
    }

    public function testOriginMismatchIsRejected(): void
    {
        $client = $this->createClientWithUser();

        $token = $this->getCsrfToken($client, 'tinymce_image_upload');
        $tempFile = $this->createTempImagePath();

        $client->request(
            'POST',
            '/articles/upload-image',
            ['_token' => $token],
            ['file' => new UploadedFile($tempFile, 'test.png', 'image/png', null, true)],
            ['HTTP_ORIGIN' => 'https://evil.test']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Origin Denied', $payload['message'] ?? null);

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
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
