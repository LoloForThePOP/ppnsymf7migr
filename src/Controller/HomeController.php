<?php

namespace App\Controller;

use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Homepage route
final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(PPBaseRepository $ppBaseRepository): Response
    {
        $presentations = $ppBaseRepository->findLatestPublished(50);

        return $this->render('home/homepage.html.twig', [
            'presentations' => $presentations,
        ]);
    }
}
