<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\AI\ProjectTaggingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AutoTagController extends AbstractController
{
    #[Route('/projects/{stringId}/ai-tags/refresh', name: 'pp_ai_tags_refresh', methods: ['POST'])]
    public function refresh(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        ProjectTaggingService $tagging,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if (!$this->isCsrfTokenValid('ai_tags_refresh_' . $presentation->getStringId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action non autorisée.');
            return $this->redirectToReferer($presentation, $request);
        }

        $suggestions = $tagging->suggestAndApply($presentation);
        $em->flush();

        $this->addFlash('success', 'Suggestions IA mises à jour : ' . count($suggestions['categories']) . ' catégorie(s), ' . count($suggestions['keywords']) . ' mot(s)-clé(s).');

        return $this->redirectToReferer($presentation, $request);
    }

    private function redirectToReferer(PPBase $presentation, Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]);
    }
}
