<?php

namespace App\Controller;

use App\Service\ProjectSearchService;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly ProjectSearchService $searchService,
        private readonly UploaderHelper $uploaderHelper,
    )
    {
    }

    #[Route('/search/projects', name: 'search_projects', methods: ['GET'])]
    public function searchProjects(Request $request): JsonResponse
    {
        $q = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 20);
        $limit = max(1, min($limit, 50));

        if (mb_strlen(trim($q)) < 2) {
            return $this->json([
                'query' => $q,
                'count' => 0,
                'results' => [],
                'message' => 'Tapez au moins 2 caractères',
            ]);
        }

        $results = $this->searchService->search($q, $limit);

        $uploader = $this->uploaderHelper;
        $payload = array_map(function ($pp) use ($uploader) {
            /** @var \App\Entity\PPBase $pp */
            $thumb = null;
            if (method_exists($pp, 'getExtra') && $pp->getExtra()?->getCacheThumbnailUrl()) {
                $thumb = $pp->getExtra()->getCacheThumbnailUrl();
            } elseif (method_exists($pp, 'getLogo') && $pp->getLogo()) {
                $thumb = $uploader->asset($pp, 'logoFile');
            }
            $url = $pp->getStringId() ? $this->generateUrl('edit_show_project_presentation', ['stringId' => $pp->getStringId()]) : null;
            return [
                'id' => $pp->getId(),
                'title' => $pp->getTitle(),
                'goal' => $pp->getGoal(),
                'stringId' => $pp->getStringId(),
                'url' => $url,
                'createdAt' => $pp->getCreatedAt()?->format(DATE_ATOM),
                'thumbnail' => $thumb,
            ];
        }, $results);

        return $this->json([
            'query' => $q,
            'count' => count($payload),
            'results' => $payload,
        ]);
    }

    #[Route('/search/demo', name: 'search_demo', methods: ['GET'])]
    public function demo(): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        return $this->render('search/demo.html.twig');
    }
}
