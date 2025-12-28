<?php

namespace App\Controller\ProjectPresentation;

use App\Service\AssessPPScoreService;
use App\Service\LiveSavePP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LiveSaveController extends AbstractController
{
    #[Route('/project/ajax-inline-save', name: 'live_save_pp', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ajaxPPLiveSave(
        LiveSavePP $liveSave,
        AssessPPScoreService $scoreService,
        Request $request
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['error' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('live_save_pp', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        // Keep malformed payloads out early to avoid propagating undefined indexes later.
        $metadataPayload = $request->request->get('metadata');
        if ($metadataPayload === null) {
            return $this->json(['error' => 'Métadonnées manquantes.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $metadata = json_decode($metadataPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Métadonnées invalides.'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($metadata)) {
            return $this->json(['error' => 'Format des métadonnées incorrect.'], Response::HTTP_BAD_REQUEST);
        }

        foreach (['entity', 'id', 'property'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $metadata)) {
                return $this->json(
                    ['error' => sprintf('La clé "%s" est requise.', $requiredKey)],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Coerce the entity identifier into a positive integer to avoid weird repository calls.
        $entityId = filter_var($metadata['id'], FILTER_VALIDATE_INT);
        if ($entityId === false || $entityId <= 0) {
            return $this->json(['error' => 'Identifiant d’élément invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $entityName = (string) $metadata['entity'];
        $property = (string) $metadata['property'];
        $subId = isset($metadata['subid']) ? (string) $metadata['subid'] : null;
        $subProperty = isset($metadata['subproperty']) ? (string) $metadata['subproperty'] : null;
        $content = trim((string) $request->request->get('content', ''));

        try {
            $liveSave->hydrate($entityName, $entityId, $property, $subId, $subProperty, $content);

            if (!$liveSave->allowUserAccess()) {
                return $this->json([], Response::HTTP_FORBIDDEN);
            }

            if (!$liveSave->allowItemAccess()) {
                return $this->json(['error' => 'Élément non autorisé.'], Response::HTTP_BAD_REQUEST);
            }

            $validationResult = $liveSave->validateContent();
            if (is_string($validationResult)) {
                return $this->json(['error' => $validationResult], Response::HTTP_BAD_REQUEST);
            }

            $liveSave->save();
            $scoreService->scoreUpdate($liveSave->getPresentation());

            $response = ['success' => true];
            if ($property === 'textDescription') {
                $response['content'] = $liveSave->getContent();
            }

            return $this->json($response);
        } catch (\InvalidArgumentException|\LogicException|\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
