<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\PPBase;
use App\Entity\User;
use App\Enum\CommentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CommentControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    private function createComment(EntityManagerInterface $em, PPBase $project, User $creator): Comment
    {
        $comment = (new Comment())
            ->setProjectPresentation($project)
            ->setCreator($creator)
            ->setStatus(CommentStatus::Approved)
            ->setContent('Test comment');

        $em->persist($comment);
        $em->flush();

        return $comment;
    }

    public function testCreateCommentMissingCsrfIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request(
            'POST',
            '/comment/ajax-create',
            [
                'commentedEntityType' => 'projectPresentation',
                'commentedEntityId' => 1,
                'commentContent' => 'Test comment',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testCreateCommentCreatesComment(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $commenter = $this->createUser($em);

        $client->loginUser($commenter);

        $token = $this->getCsrfToken($client, 'comment_mutation');

        $client->request(
            'POST',
            '/comment/ajax-create',
            [
                'commentedEntityType' => 'projectPresentation',
                'commentedEntityId' => $project->getId(),
                'commentContent' => 'Merci pour ce projet',
                'formTimeLoaded' => (string) (time() - 10),
                'hnyPt' => '',
                '_token' => $token,
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($payload['newCommentEditionUrl'] ?? null);

        $em->clear();
        $comment = $em->getRepository(Comment::class)->findOneBy([
            'creator' => $commenter,
            'projectPresentation' => $project,
            'content' => 'Merci pour ce projet',
        ]);
        self::assertNotNull($comment);
    }

    public function testDeleteCommentMissingCsrfIsRejected(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/comments/remove/',
            ['commentId' => 1],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testDeleteCommentIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $commentAuthor = $this->createUser($em);
        $comment = $this->createComment($em, $project, $commentAuthor);
        $commentId = $comment->getId();
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $token = $this->getCsrfToken($client, 'comment_mutation');

        $client->request(
            'POST',
            '/comments/remove/',
            [
                'commentId' => $commentId,
                '_token' => $token,
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);

        $em->clear();
        self::assertNotNull($em->getRepository(Comment::class)->find($commentId));
    }

    public function testProjectOwnerCanDeleteComment(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $commentAuthor = $this->createUser($em);
        $comment = $this->createComment($em, $project, $commentAuthor);
        $commentId = $comment->getId();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'comment_mutation');

        $client->request(
            'POST',
            '/comments/remove/',
            [
                'commentId' => $commentId,
                '_token' => $token,
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['feedbackCode'] ?? false);

        $em->clear();
        self::assertNull($em->getRepository(Comment::class)->find($commentId));
    }

    public function testUpdateCommentIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $commentAuthor = $this->createUser($em);
        $comment = $this->createComment($em, $project, $commentAuthor);

        $otherUser = $this->createUser($em);
        $client->loginUser($otherUser);

        $client->request('GET', sprintf('/comment/update/%d', $comment->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
