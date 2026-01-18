<?php

namespace App\Service;

use App\Entity\PPBase;
use App\Entity\PresentationEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class PresentationEventLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function log(PPBase $presentation, string $type, array $meta = [], bool $flush = false): ?PresentationEvent
    {
        if (!in_array($type, $this->getAllowedTypes(), true)) {
            return null;
        }

        $event = new PresentationEvent($type, $presentation);
        $user = $this->security->getUser();
        if ($user) {
            $event->setUser($user);
        }

        $visitorHash = $this->resolveVisitorHash();
        if ($visitorHash !== null) {
            $event->setVisitorHash($visitorHash);
        }

        if ($meta !== []) {
            $event->setMeta($meta);
        }

        $this->em->persist($event);
        if ($flush) {
            $this->em->flush();
        }

        return $event;
    }

    /**
     * @return string[]
     */
    private function getAllowedTypes(): array
    {
        return [
            PresentationEvent::TYPE_VIEW,
            PresentationEvent::TYPE_SHARE_OPEN,
            PresentationEvent::TYPE_SHARE_COPY,
            PresentationEvent::TYPE_SHARE_EXTERNAL,
        ];
    }

    private function resolveVisitorHash(): ?string
    {
        $user = $this->security->getUser();
        if ($user && method_exists($user, 'getId')) {
            $raw = 'u:' . (string) $user->getId();
            return hash('sha256', $raw);
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session) {
            return null;
        }

        $sessionId = $session->getId();
        if ($sessionId === '') {
            return null;
        }

        return hash('sha256', 's:' . $sessionId);
    }
}
