<?php

namespace App\Security\Voter;

use App\Entity\Comment;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class ManageCommentVoter extends Voter
{
    private const ATTR_UPDATE = 'update';
    private const ATTR_DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::ATTR_UPDATE, self::ATTR_DELETE], true)
            && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Comment $comment */
        $comment = $subject;
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if ($comment->getCreator() !== null && $user === $comment->getCreator()) {
            return true;
        }

        $presentation = $comment->getProjectPresentation();
        if ($presentation !== null && $presentation->getCreator() === $user) {
            return true;
        }

        $news = $comment->getNews();
        if ($news !== null) {
            $newsCreator = $news->getCreator();
            $newsProjectCreator = $news->getProject()?->getCreator();

            if (($newsCreator !== null && $newsCreator === $user)
                || ($newsProjectCreator !== null && $newsProjectCreator === $user)
            ) {
                return true;
            }
        }

        $article = $comment->getArticle();
        if ($article !== null && $article->getCreator() === $user) {
            return true;
        }

        return false;
    }

    private function isAdmin(UserInterface $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
