<?php

namespace App\Twig\Components;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsLiveComponent('follow_button')]
final class FollowButton
{
    use DefaultActionTrait;

    #[LiveProp(writable: false)]
    public PPBase $presentation;

    #[LiveProp]
    public bool $isFollowed = false;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function mount(PPBase $presentation): void
    {
        $user = $this->security->getUser();
        $this->presentation = $presentation;
        $this->isFollowed = $user ? $presentation->isFollowedBy($user) : false;
    }

    #[LiveAction]
    public function toggle(): mixed
    {
        $user = $this->security->getUser();

        // Redirect if anonymous
        if (!$user) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $this->isFollowed = $this->presentation->toggleFollow($user);
        $this->em->flush();

        return null;
    }
}
