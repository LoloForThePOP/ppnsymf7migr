<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\PresentationEventLogger;
use App\Entity\PresentationEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PresentationEventController extends AbstractController
{
    #[Route('/pp/{stringId}/event', name: 'pp_event', methods: ['POST'])]
    public function logEvent(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        PresentationEventLogger $eventLogger,
        EntityManagerInterface $em,
    ): JsonResponse {
        $payload = [];
        $content = $request->getContent();
        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if ($payload === []) {
            $payload = $request->request->all();
        }

        $type = $payload['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return $this->json(['ok' => false, 'error' => 'missing_type'], 400);
        }

        $meta = $payload['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $event = $eventLogger->log($presentation, $type, $meta, true);
        if (!$event instanceof PresentationEvent) {
            return $this->json(['ok' => false, 'error' => 'invalid_type'], 400);
        }

        return $this->json(['ok' => true]);
    }
}
