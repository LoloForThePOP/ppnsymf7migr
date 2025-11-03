<?php

namespace App\Security\Voter;

use App\Entity\PPBase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls who can view or edit a project presentation (PPBase).
 */
final class AccessProjectPresentationVoter extends Voter
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['view', 'edit'], true)
            && $subject instanceof PPBase;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var PPBase $presentation */
        $presentation = $subject;
        $user = $token->getUser();

        return match ($attribute) {
            'view' => $this->canView($presentation, $user, $token),
            'edit' => $this->canEdit($presentation, $user),
            default => false,
        };
    }

    // ───────────────────────────────────────────────
    // EDIT RIGHTS
    // ───────────────────────────────────────────────

    private function canEdit(PPBase $presentation, mixed $user): bool
    {
        // Deleted presentations cannot be edited
        if ($presentation->isDeleted()) {
            return false;
        }

        // Authenticated users only beyond this point
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Creator of the presentation
        if ($user === $presentation->getCreator()) {
            return true;
        }

        // Admin override
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return false;
    }

    // ───────────────────────────────────────────────
    // VIEW RIGHTS
    // ───────────────────────────────────────────────

    private function canView(PPBase $presentation, mixed $user, TokenInterface $token): bool
    {
        // If user can edit, they can also view
        if ($this->canEdit($presentation, $user)) {
            return true;
        }

        // Only published presentations can be viewed publicly
        return $presentation->isPublished();
    }


}
