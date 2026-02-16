<?php

namespace App\Controller;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryTabController extends AbstractController
{
    private const RESULTS_LIMIT = 16;

    public function __construct(private readonly PPBaseRepository $repo)
    {
    }

    #[Route('/tabs/category/{category}', name: 'category_tab', methods: ['GET'])]
    public function __invoke(string $category): Response
    {
        $cats = ($category === '' || $category === 'all') ? [] : [$category];
        $items = $this->repo->findPublishedByCategories($cats, self::RESULTS_LIMIT);
        $ids = $this->extractPresentationIds($items);
        $this->repo->warmupCategoriesForIds($ids);
        $stats = $this->repo->getEngagementCountsForIds($ids);

        return $this->render('home/_projects_by_category_tabs/_each_tab_content.html.twig', [
            'items' => $items,
            'presentationStats' => $stats,
        ]);
    }

    /**
     * @param PPBase[] $items
     *
     * @return int[]
     */
    private function extractPresentationIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (!$item instanceof PPBase) {
                continue;
            }

            $itemId = $item->getId();
            if ($itemId === null || isset($ids[$itemId])) {
                continue;
            }

            $ids[$itemId] = true;
        }

        return array_keys($ids);
    }
}
