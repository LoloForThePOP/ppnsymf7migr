<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Service\ScraperIngestionService;
use App\Service\ScraperPersistenceService;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/harvest/run', name: 'admin_harvest_run', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
class HarvestRunController extends AbstractController
{
    public function __invoke(
        Request $request,
        ScraperIngestionService $ingestion,
        ScraperPersistenceService $persistence,
        UserRepository $users,
        PPBaseRepository $projects,
        int $defaultCreatorId
    ): Response {
        $creator = $users->find($defaultCreatorId);
        if (!$creator) {
            $this->addFlash('warning', 'Créateur par défaut introuvable.');
            return $this->redirectToRoute('admin_harvest');
        }

        $result = $ingestion->fetchAndNormalize();
        $persistResult = $persistence->persist($result['items'], $creator);

        return $this->render('admin/harvest_run.html.twig', [
            'ingestionErrors' => $result['errors'],
            'persistResult' => $persistResult,
        ]);
    }
}
