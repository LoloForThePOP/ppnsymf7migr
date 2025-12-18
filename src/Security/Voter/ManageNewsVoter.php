<?php

namespace App\Security\Voter;

use App\Entity\News;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class ManageNewsVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['delete', 'edit'], true)
            && $subject instanceof News;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var News $news */
        $news = $subject;
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->hasRole($user, 'ROLE_ADMIN') || $this->hasRole($user, 'ROLE_NEWS_MANAGE')) {
            return true;
        }

        $projectCreator = $news->getProject()?->getCreator();
        if ($projectCreator !== null && $user === $projectCreator) {
            return true;
        }

        if ($news->getCreator() !== null && $user === $news->getCreator()) {
            return true;
        }

        return false;
    }

    private function hasRole(UserInterface $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }
}
