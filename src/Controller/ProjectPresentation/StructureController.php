<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\ProjectPresentationStructureService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StructureController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/structure/reorder',
        name: 'pp_structure_reorder',
        methods: ['POST']
    )]
    public function reorder(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        ProjectPresentationStructureService $structureService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if (!$request->isXmlHttpRequest()) {
            return $this->json(['error' => 'Requête invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('pp_structure_mutation', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $scope = (string) $request->request->get('scope', '');
        $orderedIds = $request->request->all('orderedIds');

        if ($scope === '' || $orderedIds === []) {
            return $this->json(['error' => 'Données manquantes.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $structureService->reorder($presentation, $scope, $orderedIds);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json(['success' => true]);
    }

    #[Route(
        '/projects/{stringId}/structure/delete',
        name: 'pp_structure_delete',
        methods: ['POST']
    )]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        ProjectPresentationStructureService $structureService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if (!$request->isXmlHttpRequest()) {
            return $this->json(['error' => 'Requête invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('pp_structure_mutation', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $scope = (string) $request->request->get('scope', '');
        $itemId = (string) $request->request->get('id', '');

        if ($scope === '' || $itemId === '') {
            return $this->json(['error' => 'Données manquantes.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $structureService->delete($presentation, $scope, $itemId);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json(['success' => true]);
    }
}
