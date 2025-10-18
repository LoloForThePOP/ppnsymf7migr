<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateProjectPresentationController extends AbstractController
{
    #[Route('/create/project/presentation', name: 'project_presentation_helper')]
    public function index(): Response
    {
        // to fill
        return $this->redirectToRoute('homepage');

    }
}
