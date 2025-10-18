<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AjaxChunksController extends AbstractController
{
    /**
     * Render an html chunk via an ajax call to reduce page load.
     */
    #[Route('/ajax-render-chunk', name: 'ajax_render_chunk')]
    public function index(): Response
    {

        // to fill
        
        return new JsonResponse();
    }
}
