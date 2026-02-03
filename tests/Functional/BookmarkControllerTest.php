<?php

namespace App\Tests\Functional;

use App\Entity\Bookmark;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class BookmarkControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);

        $client->request('POST', sprintf('/project/%s/bookmark', $project->getStringId()));

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testMissingCsrfIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $user = $this->createUser($em);

        $client->loginUser($user);
        $client->request('POST', sprintf('/project/%s/bookmark', $project->getStringId()));

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Invalid CSRF token', $payload['error'] ?? null);
    }

    public function testBookmarkToggleCreatesAndRemoves(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $user = $this->createUser($em);

        $client->loginUser($user);
        $token = $this->getCsrfToken($client, 'bookmark' . $project->getStringId());

        $client->request('POST', sprintf('/project/%s/bookmark', $project->getStringId()), [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('created', $payload['action'] ?? null);

        $client->request('POST', sprintf('/project/%s/bookmark', $project->getStringId()), [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('removed', $payload['action'] ?? null);
    }

    public function testBookmarksPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-bookmarks');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testBookmarksPageDisplaysOnlyCurrentUserBookmarksAndSkipsDeletedProjects(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $user = $this->createUser($em);
        $otherUser = $this->createUser($em);

        $visibleGoal = 'visible-bookmarked-goal';
        $deletedGoal = 'deleted-bookmarked-goal';
        $otherUserGoal = 'other-user-bookmarked-goal';
        $nonBookmarkedGoal = 'not-bookmarked-goal';

        $visibleProject = $this->createProject($em, $creator, $visibleGoal);
        $deletedProject = $this->createProject($em, $creator, $deletedGoal);
        $deletedProject->setIsDeleted(true);
        $otherUserProject = $this->createProject($em, $creator, $otherUserGoal);
        $nonBookmarkedProject = $this->createProject($em, $creator, $nonBookmarkedGoal);

        $em->persist((new Bookmark())->setUser($user)->setProjectPresentation($visibleProject));
        $em->persist((new Bookmark())->setUser($user)->setProjectPresentation($deletedProject));
        $em->persist((new Bookmark())->setUser($otherUser)->setProjectPresentation($otherUserProject));
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/my-bookmarks');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Mes marque-pages', $content);
        self::assertStringContainsString($visibleGoal, $content);
        self::assertStringNotContainsString($deletedGoal, $content);
        self::assertStringNotContainsString($otherUserGoal, $content);
        self::assertStringNotContainsString($nonBookmarkedGoal, $content);
    }

    public function testNavbarShowsMesFavorisForLoggedUser(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mes marque-pages', (string) $client->getResponse()->getContent());
    }

    public function testNavbarHidesMesFavorisForAnonymousUser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Mes marque-pages', (string) $client->getResponse()->getContent());
    }
}
