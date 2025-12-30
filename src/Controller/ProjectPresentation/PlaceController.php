<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Place;
use App\Entity\PPBase;
use App\Repository\PlaceRepository;
use App\Entity\Embeddables\GeoPoint;
use App\Service\AssessPPScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PlaceController extends AbstractController
{
    #[Route('/projects/{stringId}/places/ajax-new-place', name: 'ajax_add_place', methods: ['POST'])]
    public function ajaxNewPlace(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $entityManager,
        AssessPPScoreService $scoreService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if (!$request->isXmlHttpRequest()) {
            return $this->json(['error' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('pp_place_mutation', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->request;
        $latitude = filter_var($payload->get('latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload->get('longitude'), FILTER_VALIDATE_FLOAT);
        $type = trim((string) $payload->get('type', ''));
        $name = trim((string) $payload->get('name', ''));

        if ($type === '' || $name === '' || $latitude === false || $longitude === false) {
            return $this->json(['error' => 'Données incomplètes.'], Response::HTTP_BAD_REQUEST);
        }

        $newPlace = (new Place())
            ->setType($type)
            ->setName($name)
            ->setGeoloc(new GeoPoint($latitude, $longitude))
            ->setCountry((string) $payload->get('country'))
            ->setAdministrativeAreaLevel1((string) $payload->get('administrativeAreaLevel1'))
            ->setAdministrativeAreaLevel2((string) $payload->get('administrativeAreaLevel2'))
            ->setLocality((string) $payload->get('locality'))
            ->setSublocalityLevel1((string) $payload->get('sublocalityLevel1'))
            ->setPostalCode((string) $payload->get('postalCode'));

        $presentation->addPlace($newPlace);
        $entityManager->persist($newPlace);
        $scoreService->scoreUpdate($presentation);
        $entityManager->flush();

        return $this->json([
            'placeId' => $newPlace->getId(),
            'feedbackCode' => true,
        ]);
    }

    #[Route('/projects/{stringId}/places/ajax-remove-place', name: 'ajax_remove_place', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $entityManager,
        PlaceRepository $placeRepository,
        AssessPPScoreService $scoreService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if (!$request->isXmlHttpRequest()) {
            return $this->json(['error' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('pp_place_mutation', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $placeId = filter_var($request->request->get('placeId'), FILTER_VALIDATE_INT);
        if ($placeId === false) {
            return $this->json(['error' => 'Identifiant de lieu invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $place = $placeRepository->find($placeId);
        if ($place === null || !$presentation->getPlaces()->contains($place)) {
            return $this->json(['error' => 'Lieu introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $presentation->removePlace($place);
        $entityManager->remove($place);
        $scoreService->scoreUpdate($presentation);
        $entityManager->flush();

        return $this->json([
            'deletedPlaceId' => $placeId,
            'feedbackCode' => true,
        ]);
    }
}
