<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Homepage route
final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(PPBaseRepository $ppBaseRepository, ArticleRepository $articleRepository): Response
    {
        $presentations = $ppBaseRepository->findLatestPublished(50);
        $articles = $articleRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);


        return $this->render('home/homepage.html.twig', [
            'presentations' => $presentations,
            'articles' => $articles,
        ]);
    }
}
