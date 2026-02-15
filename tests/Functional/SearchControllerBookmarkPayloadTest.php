<?php

namespace App\Tests\Functional;

use App\Entity\Bookmark;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SearchControllerBookmarkPayloadTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testAnonymousSearchPayloadContainsLoginBookmarkMetadata(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator, 'Test project goal for bookmark metadata.');

        $client->request('GET', '/search/projects?q=test&limit=8');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload['results'] ?? null);
        self::assertNotEmpty($payload['results']);

        $row = $this->findResultById($payload['results'], (int) $project->getId());
        self::assertNotNull($row);

        $bookmark = $row['bookmark'] ?? null;
        self::assertIsArray($bookmark);
        self::assertTrue($bookmark['enabled'] ?? false);
        self::assertFalse($bookmark['isAuthenticated'] ?? true);
        self::assertTrue(!array_key_exists('url', $bookmark) || $bookmark['url'] === null);
        self::assertTrue(!array_key_exists('token', $bookmark) || $bookmark['token'] === null);
        self::assertSame('/login', $bookmark['loginUrl'] ?? null);
    }

    public function testAuthenticatedSearchPayloadContainsToggleBookmarkMetadata(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator, 'Test project goal for bookmark metadata.');
        $viewer = $this->createUser($em);

        $bookmark = (new Bookmark())
            ->setUser($viewer)
            ->setProjectPresentation($project);
        $em->persist($bookmark);
        $em->flush();
        $client->loginUser($viewer);

        $client->request('GET', '/search/projects?q=test&limit=8');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload['results'] ?? null);
        self::assertNotEmpty($payload['results']);

        $row = $this->findResultById($payload['results'], (int) $project->getId());
        self::assertNotNull($row);

        $bookmarkMeta = $row['bookmark'] ?? null;
        self::assertIsArray($bookmarkMeta);
        self::assertTrue($bookmarkMeta['enabled'] ?? false);
        self::assertTrue($bookmarkMeta['isAuthenticated'] ?? false);
        self::assertIsString($bookmarkMeta['url'] ?? null);
        self::assertStringContainsString('/project/' . $project->getStringId() . '/bookmark/add', (string) $bookmarkMeta['url']);
        self::assertIsString($bookmarkMeta['token'] ?? null);
        self::assertNotSame('', trim((string) $bookmarkMeta['token']));
        self::assertTrue(!array_key_exists('loginUrl', $bookmarkMeta) || $bookmarkMeta['loginUrl'] === null);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    private function findResultById(array $results, int $projectId): ?array
    {
        foreach ($results as $row) {
            if ((int) ($row['id'] ?? 0) === $projectId) {
                return $row;
            }
        }

        return null;
    }
}
