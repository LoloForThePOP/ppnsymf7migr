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

final class UserSearchHistoryController extends AbstractController
{
    #[Route('/account/search-history', name: 'user_search_history', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserExtraService $userExtraService,
    ): JsonResponse {
        $user = $this->getUser();
        $profile = $user?->getProfile();

        if ($profile === null) {
            return new JsonResponse(['error' => 'Profil introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        if ($request->isMethod('GET')) {
            $history = $this->normalizeHistory($userExtraService->get($profile, 'search_history', []));
            return new JsonResponse(['history' => $history]);
        }

        $payload = $this->extractPayload($request);
        $token = (string) ($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('user_search_history', $token)) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $action = trim((string) ($payload['action'] ?? 'add'));
        $term = trim((string) ($payload['term'] ?? ''));

        $history = $this->normalizeHistory($userExtraService->get($profile, 'search_history', []));

        if ($action === 'remove') {
            $history = $this->removeTerm($history, $term);
        } elseif ($action === 'replace') {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $history = $this->normalizeHistory($items);
        } else {
            $history = $this->prependTerm($history, $term);
        }

        $userExtraService->set($profile, 'search_history', $history);
        $em->flush();

        return new JsonResponse(['history' => $history]);
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

    /**
     * @param array<int, mixed> $history
     * @return array<int, string>
     */
    private function normalizeHistory(array $history): array
    {
        $normalized = [];
        $seen = [];

        foreach ($history as $item) {
            if (!is_string($item)) {
                continue;
            }
            $term = trim($item);
            if ($term === '' || mb_strlen($term) < 2) {
                continue;
            }
            $key = mb_strtolower($term);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = $term;
            if (count($normalized) >= 10) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $history
     * @return array<int, string>
     */
    private function prependTerm(array $history, string $term): array
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) < 2) {
            return $history;
        }
        array_unshift($history, $term);
        return $this->normalizeHistory($history);
    }

    /**
     * @param array<int, string> $history
     * @return array<int, string>
     */
    private function removeTerm(array $history, string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return $history;
        }
        $target = mb_strtolower($term);
        $filtered = array_filter($history, static function ($item) use ($target) {
            return mb_strtolower($item) !== $target;
        });
        return array_values($this->normalizeHistory($filtered));
    }
}
