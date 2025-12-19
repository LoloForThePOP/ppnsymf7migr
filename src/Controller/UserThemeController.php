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

final class UserThemeController extends AbstractController
{
    #[Route('/account/theme', name: 'user_theme_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateTheme(
        Request $request,
        EntityManagerInterface $em,
        UserExtraService $userExtraService,
    ): JsonResponse {
        $payload = $this->extractPayload($request);
        $token = (string) ($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('update_theme', $token)) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $theme = $this->normalizeTheme($payload['theme'] ?? '');
        $allowedThemes = ['classic', 'sand', 'mint', 'slate'];

        if (!in_array($theme, $allowedThemes, true)) {
            return new JsonResponse(['error' => 'Thème invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $profile = $user?->getProfile();

        if ($profile === null) {
            return new JsonResponse(['error' => 'Profil introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $userExtraService->set($profile, 'theme', $theme);
        $em->flush();

        return new JsonResponse(['success' => true, 'theme' => $theme]);
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

    private function normalizeTheme(mixed $theme): string
    {
        $theme = trim((string) $theme);
        return $theme === 'light' ? 'classic' : $theme;
    }
}
