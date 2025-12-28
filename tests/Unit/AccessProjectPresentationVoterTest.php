<?php

namespace App\Tests\Unit;

use App\Entity\PPBase;
use App\Entity\User;
use App\Security\Voter\AccessProjectPresentationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AccessProjectPresentationVoterTest extends TestCase
{
    public function testEditGrantedToCreator(): void
    {
        $creator = $this->makeUser();
        $presentation = (new PPBase())
            ->setGoal('A test goal for edit access.')
            ->setCreator($creator);

        $voter = new AccessProjectPresentationVoter(new RequestStack());
        $token = $this->tokenForUser($creator);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $presentation, ['edit'])
        );
    }

    public function testEditDeniedWhenDeleted(): void
    {
        $creator = $this->makeUser();
        $presentation = (new PPBase())
            ->setGoal('A test goal for edit access.')
            ->setCreator($creator)
            ->setIsDeleted(true);

        $voter = new AccessProjectPresentationVoter(new RequestStack());
        $token = $this->tokenForUser($creator);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $presentation, ['edit'])
        );
    }

    public function testEditGrantedToAdmin(): void
    {
        $creator = $this->makeUser();
        $admin = $this->makeUser(['ROLE_ADMIN']);

        $presentation = (new PPBase())
            ->setGoal('A test goal for edit access.')
            ->setCreator($creator);

        $voter = new AccessProjectPresentationVoter(new RequestStack());
        $token = $this->tokenForUser($admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $presentation, ['edit'])
        );
    }

    public function testEditDeniedToNonOwner(): void
    {
        $creator = $this->makeUser();
        $otherUser = $this->makeUser();

        $presentation = (new PPBase())
            ->setGoal('A test goal for edit access.')
            ->setCreator($creator);

        $voter = new AccessProjectPresentationVoter(new RequestStack());
        $token = $this->tokenForUser($otherUser);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $presentation, ['edit'])
        );
    }

    private function makeUser(array $roles = []): User
    {
        $user = (new User())
            ->setEmail(sprintf('voter+%s@example.com', uniqid('', true)))
            ->setUsername(sprintf('voter_%s', uniqid('', true)))
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
