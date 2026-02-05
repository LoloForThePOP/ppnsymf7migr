<?php

namespace App\Controller\Admin;

use App\Service\Scraping\Core\ScraperIngestionService;
use App\Service\Scraping\Core\ScraperPersistenceService;
use App\Service\Scraping\Common\ScraperUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;

#[Route('/admin/harvest/run', name: 'admin_harvest_run', methods: ['POST'])]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class HarvestRunController extends AbstractController
{
    public function __invoke(
        Request $request,
        ScraperIngestionService $ingestion,
        ScraperPersistenceService $persistence,
        ScraperUserResolver $scraperUserResolver
    ): Response {
        $creator = $scraperUserResolver->resolve();
        if (!$creator) {
            $this->addFlash('warning', sprintf(
                'Compte "%s" introuvable ou multiple.',
                $scraperUserResolver->getRole()
            ));
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
