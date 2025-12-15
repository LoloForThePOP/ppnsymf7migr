<?php

namespace App\Controller;

use App\Entity\PPBase;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryTabController extends AbstractController
{
    public function __construct(private readonly PPBaseRepository $repo)
    {
    }

    #[Route('/tabs/category/{category}', name: 'category_tab', methods: ['GET'])]
    public function __invoke(string $category): Response
    {
        $cats = ($category === '' || $category === 'all') ? [] : [$category];
        $items = $this->repo->findPublishedByCategories($cats, 16);
        $ids = array_map(static fn (PPBase $pp) => $pp->getId(), $items);
        $stats = $this->repo->getEngagementCountsForIds($ids);

        return $this->render('home/_projects_by_category_tabs/_each_tab_content.html.twig', [
            'items' => $items,
            'presentationStats' => $stats,
        ]);
    }
}
