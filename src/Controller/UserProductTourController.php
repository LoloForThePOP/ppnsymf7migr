<?php

namespace App\Controller;

use App\Service\UserExtraService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserProductTourController extends AbstractController
{
    #[Route('/account/product-tour', name: 'user_product_tour_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markSeen(
        Request $request,
        EntityManagerInterface $em,
        UserExtraService $userExtraService,
    ): JsonResponse {
        $payload = $this->extractPayload($request);
        $token = (string) ($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('user_product_tour_update', $token)) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $tour = trim((string) ($payload['tour'] ?? ''));
        $version = trim((string) ($payload['version'] ?? 'v1'));

        if ($tour === '' || mb_strlen($tour) > 64) {
            return new JsonResponse(['error' => 'Identifiant de parcours invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $profile = $user?->getProfile();

        if ($profile === null) {
            return new JsonResponse(['error' => 'Profil introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $userExtraService->get($profile, 'product_tours', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[$tour] = $version;

        $userExtraService->set($profile, 'product_tours', $existing);
        $em->flush();

        return new JsonResponse(['success' => true, 'tour' => $tour, 'version' => $version]);
    }

    private function extractPayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }
}
