<?php

namespace App\Tests\Unit;

use App\Entity\Comment;
use App\Entity\PPBase;
use App\Entity\User;
use App\Security\Voter\ManageCommentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ManageCommentVoterTest extends TestCase
{
    public function testDeleteGrantedToCommentCreator(): void
    {
        $creator = $this->makeUser();
        $comment = (new Comment())
            ->setContent('Test comment')
            ->setCreator($creator);

        $voter = new ManageCommentVoter();
        $token = $this->tokenForUser($creator);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $comment, ['delete'])
        );
    }

    public function testDeleteGrantedToProjectOwner(): void
    {
        $projectOwner = $this->makeUser();
        $commenter = $this->makeUser();

        $presentation = (new PPBase())
            ->setGoal('Test goal for comment access.')
            ->setCreator($projectOwner);

        $comment = (new Comment())
            ->setContent('Test comment')
            ->setCreator($commenter)
            ->setProjectPresentation($presentation);

        $voter = new ManageCommentVoter();
        $token = $this->tokenForUser($projectOwner);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $comment, ['delete'])
        );
    }

    public function testDeleteGrantedToAdmin(): void
    {
        $admin = $this->makeUser(['ROLE_ADMIN']);
        $commenter = $this->makeUser();

        $comment = (new Comment())
            ->setContent('Test comment')
            ->setCreator($commenter);

        $voter = new ManageCommentVoter();
        $token = $this->tokenForUser($admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $comment, ['delete'])
        );
    }

    public function testDeleteDeniedToUnrelatedUser(): void
    {
        $commenter = $this->makeUser();
        $otherUser = $this->makeUser();

        $comment = (new Comment())
            ->setContent('Test comment')
            ->setCreator($commenter);

        $voter = new ManageCommentVoter();
        $token = $this->tokenForUser($otherUser);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $comment, ['delete'])
        );
    }

    private function makeUser(array $roles = []): User
    {
        $user = (new User())
            ->setEmail(sprintf('comment-voter+%s@example.com', uniqid('', true)))
            ->setUsername(sprintf('comment_voter_%s', uniqid('', true)))
            ->setPassword('dummy')
            ->setIsActive(true)
            ->setIsVerified(true);

        if ($roles !== []) {
            $user->setRoles($roles);
        }

        return $user;
    }

    private function tokenForUser(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
