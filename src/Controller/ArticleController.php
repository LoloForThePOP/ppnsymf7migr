<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    #[Route('/article/index-articles', name: 'index_articles')]
    public function index(): Response
    {
        // to fill
        return $this->redirectToRoute('homepage', []);
    }
}
