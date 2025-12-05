<?php

namespace App\Security\Voter;

use App\Entity\Article;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Controls who can edit/validate/delete articles.
 */
class ManageArticleVoter extends Voter
{
    private const ATTR_USER_EDIT  = 'user_edit';
    private const ATTR_ADMIN_EDIT = 'admin_edit';
    private const ATTR_DELETE     = 'delete';

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::ATTR_USER_EDIT, self::ATTR_ADMIN_EDIT, self::ATTR_DELETE], true)) {
            return false;
        }

        return $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        /** @var Article $article */
        $article = $subject;

        return match ($attribute) {
            self::ATTR_USER_EDIT  => $this->canUserEdit($article, $token),
            self::ATTR_ADMIN_EDIT => $this->canAdminEdit($article, $token),
            self::ATTR_DELETE     => $this->canDelete($article, $token),
            default               => false,
        };
    }

    private function canUserEdit(Article $article, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->isArticleEditor($user)) {
            return true;
        }

        return $user === $article->getCreator();
    }

    private function canAdminEdit(Article $article, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof UserInterface && $this->isArticleEditor($user);
    }

    private function canDelete(Article $article, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->isArticleEditor($user)) {
            return true;
        }

        return $user === $article->getCreator();
    }

    /**
     * Roles allowed to administer articles.
     */
    private function isArticleEditor(UserInterface $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_SUPER_ADMIN', $roles, true)
            || in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_ARTICLE_EDIT', $roles, true);
    }
}
