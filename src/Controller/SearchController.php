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
        $limit = (int) $request->query->get('limit', 16);
        $limit = max(1, min($limit, 16));
        $page = (int) $request->query->get('page', 1);
        $page = max(1, $page);

        $queryParams = $request->query->all();
        $rawCategories = $queryParams['categories'] ?? [];
        if (is_string($rawCategories)) {
            $categories = array_filter(array_map('trim', explode(',', $rawCategories)));
        } elseif (is_array($rawCategories)) {
            $categories = $rawCategories;
        } else {
            $categories = [];
        }
        $categories = array_values(array_filter(
            array_map(static fn ($value) => trim((string) $value), $categories),
            static fn ($value) => $value !== ''
        ));

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius');
        $location = null;
        if (is_numeric($lat) && is_numeric($lng)) {
            $location = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'radius' => is_numeric($radius) ? (float) $radius : null,
            ];
        }

        $hasText = mb_strlen(trim($q)) >= 2;
        if (!$hasText && $location === null) {
            return $this->json([
                'query' => $q,
                'count' => 0,
                'total' => 0,
                'page' => 1,
                'pages' => 0,
                'limit' => $limit,
                'results' => [],
                'message' => 'Tapez au moins 2 caractères ou choisissez une localisation',
            ]);
        }

        $result = $this->searchService->searchWithFilters($q, $categories, $limit, $page, $location);
        $results = $result['items'];
        $total = $result['total'];
        $page = $result['page'];
        $pages = $result['pages'];
        $totalBase = $result['totalBase'] ?? $total;
        $categoryCounts = $result['categoryCounts'] ?? [];

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
            $categories = [];
            foreach ($pp->getCategories() as $category) {
                $categories[] = [
                    'uniqueName' => $category->getUniqueName(),
                    'label' => $category->getLabel() ?? $category->getUniqueName(),
                ];
            }
            $location = null;
            foreach ($pp->getPlaces() as $place) {
                $geoloc = $place->getGeoloc();
                if ($geoloc->isDefined()) {
                    $parts = array_filter([
                        $place->getName(),
                        $place->getLocality(),
                        $place->getCountry(),
                    ]);
                    $location = [
                        'lat' => $geoloc->getLatitude(),
                        'lng' => $geoloc->getLongitude(),
                        'label' => $parts ? implode(', ', array_unique($parts)) : null,
                    ];
                    break;
                }
            }
            return [
                'id' => $pp->getId(),
                'title' => $pp->getTitle(),
                'goal' => $pp->getGoal(),
                'stringId' => $pp->getStringId(),
                'url' => $url,
                'createdAt' => $pp->getCreatedAt()?->format(DATE_ATOM),
                'thumbnail' => $thumb,
                'categories' => $categories,
                'location' => $location,
            ];
        }, $results);

        return $this->json([
            'query' => $q,
            'count' => count($payload),
            'total' => $total,
            'totalBase' => $totalBase,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'results' => $payload,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    #[Route('/search/demo', name: 'search_demo', methods: ['GET'])]
    public function demo(): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        return $this->render('search/demo.html.twig');
    }

    #[Route('/search/suggest', name: 'search_suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $q = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 8);
        $limit = max(1, min($limit, 12));

        $suggestions = $this->searchService->suggest($q, $limit);

        return $this->json([
            'query' => $q,
            'suggestions' => $suggestions,
        ]);
    }
}
