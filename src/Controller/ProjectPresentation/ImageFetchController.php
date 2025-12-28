<?php

namespace App\Controller\ProjectPresentation;

use App\Repository\PPBaseRepository;
use App\Service\ImageCandidateFetcher;
use App\Service\ImageDownloader;
use App\Service\WebpageContentExtractor;
use App\Service\WebsiteProcessingService;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImageFetchController extends AbstractController
{
    #[Route('/project/{stringId}/images', name: 'project_fetch_images', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(
        string $stringId,
        PPBaseRepository $repository,
        ImageCandidateFetcher $imageCandidateFetcher,
        WebpageContentExtractor $extractor,
        Request $request
    ): Response {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('project_fetch_images', $token)) {
                return $this->denyInvalidCsrf($request, $stringId);
            }
        }

        $project = $repository->findOneBy(['stringId' => $stringId]);

        if (!$project) {
            throw $this->createNotFoundException();
        }

        $sourceUrl = $project->getIngestion()->getSourceUrl() ?? null;
        $postedHtml = $request->request->get('page_html');
        $postedHtml = is_string($postedHtml) ? trim($postedHtml) : '';
        $fromPastedHtml = false;

        if ($request->isMethod('POST') && $postedHtml !== '') {
            $fromPastedHtml = true;
            $candidates = $imageCandidateFetcher->extractFromHtml($postedHtml, $sourceUrl);
        } else {
            $candidates = $sourceUrl ? $imageCandidateFetcher->fetch($sourceUrl) : [];
        }

        // Extract links for selection
        $links = [];
        $sourceHtml = $postedHtml !== '' ? $postedHtml : ($sourceUrl ? $imageCandidateFetcher->fetchPage($sourceUrl) : null);
        if ($sourceHtml) {
            $extracted = $extractor->extract($sourceHtml, $sourceUrl);
            $links = $extracted['links'] ?? [];
        }

        $viewData = [
            'project' => $project,
            'sourceUrl' => $sourceUrl,
            'candidates' => $candidates,
            'pageHtml' => $postedHtml,
            'fromPastedHtml' => $fromPastedHtml,
            'links' => $links,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('project_presentation/automation/images/_candidates.html.twig', $viewData);
        }

        return $this->render('project_presentation/automation/images/fetch.html.twig', $viewData);
    }

    #[Route('/project/{stringId}/images/delete', name: 'project_delete_from_images', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        string $stringId,
        Request $request,
        PPBaseRepository $repository
    ): RedirectResponse
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('project_delete_from_images', $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('project_fetch_images', ['stringId' => $stringId]);
        }

        $project = $repository->findOneBy(['stringId' => $stringId]);

        if ($project) {
            $repository->remove($project, true);
            $this->addFlash('success', sprintf('Projet "%s" supprimé.', $project->getTitle() ?? $project->getGoal()));
        } else {
            $this->addFlash('warning', 'Projet introuvable.');
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/project/{stringId}/images/use-as-logo', name: 'project_image_use_as_logo', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function useAsLogo(
        string $stringId,
        Request $request,
        PPBaseRepository $repository,
        ImageDownloader $downloader,
        ImageCandidateFetcher $imageCandidateFetcher
    ): Response {
        if (!$this->isCsrfTokenValid('project_image_use_as_logo', (string) $request->request->get('_token'))) {
            return $this->denyInvalidCsrf($request, $stringId);
        }

        $project = $repository->findOneBy(['stringId' => $stringId]);
        if (!$project) {
            throw $this->createNotFoundException();
        }

        $url = trim((string) $request->request->get('image_url', ''));
        $file = $url ? $downloader->download($url) : null;

        if ($file) {
            $project->setLogoFile($file);
            $repository->getEntityManager()->flush();
            $this->addFlash('success', 'Logo mis à jour.');
        } else {
            $this->addFlash('warning', 'Impossible de télécharger ce logo.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->renderCandidatesPartial($request, $project, $imageCandidateFetcher);
        }

        return $this->redirectToRoute('project_fetch_images', ['stringId' => $stringId]);
    }

    #[Route('/project/{stringId}/images/add-slide', name: 'project_image_add_slide', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addSlide(
        string $stringId,
        Request $request,
        PPBaseRepository $repository,
        ImageDownloader $downloader,
        ImageCandidateFetcher $imageCandidateFetcher
    ): Response {
        if (!$this->isCsrfTokenValid('project_image_add_slide', (string) $request->request->get('_token'))) {
            return $this->denyInvalidCsrf($request, $stringId);
        }

        $project = $repository->findOneBy(['stringId' => $stringId]);
        if (!$project) {
            throw $this->createNotFoundException();
        }

        $url = trim((string) $request->request->get('image_url', ''));
        $file = $url ? $downloader->download($url) : null;

        if ($file) {
            // Respect slide cap (8)
            if ($project->getSlides()->count() >= 8) {
                $this->addFlash('warning', 'Limite de 8 slides atteinte.');
            } else {
                $slide = new \App\Entity\Slide();
                $slide->setType(\App\Enum\SlideType::IMAGE);
                $slide->setPosition($project->getSlides()->count());
                $slide->setImageFile($file);
                $slide->setProjectPresentation($project);
                $project->addSlide($slide);
                $repository->getEntityManager()->flush();
                $this->addFlash('success', 'Image ajoutée comme slide.');
            }
        } else {
            $this->addFlash('warning', 'Impossible de télécharger cette image.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->renderCandidatesPartial($request, $project, $imageCandidateFetcher);
        }

        return $this->redirectToRoute('project_fetch_images', ['stringId' => $stringId]);
    }

    #[Route('/project/{stringId}/images/add-links', name: 'project_image_add_links', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addLinks(
        string $stringId,
        Request $request,
        PPBaseRepository $repository,
        ImageCandidateFetcher $imageCandidateFetcher,
        WebsiteProcessingService $websiteProcessingService
    ): Response {
        if (!$this->isCsrfTokenValid('project_image_add_links', (string) $request->request->get('_token'))) {
            return $this->denyInvalidCsrf($request, $stringId);
        }

        $project = $repository->findOneBy(['stringId' => $stringId]);
        if (!$project) {
            throw $this->createNotFoundException();
        }

        $links = $request->request->all('links');
        $links = array_filter($links, fn($l) => is_string($l) && $l !== '');

        $oc = $project->getOtherComponents();
        foreach ($links as $link) {
            $host = parse_url($link, PHP_URL_HOST) ?? $link;
            $title = $host ? strtolower(preg_replace('#^www\.#', '', $host)) : $link;
            $component = \App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent::createNew($title, $link);
            $websiteProcessingService->process($component);
            $oc->addComponent('websites', $component);
        }

        $project->setOtherComponents($oc);
        $repository->getEntityManager()->flush();

        $this->addFlash('success', 'Liens ajoutés.');

        if ($request->isXmlHttpRequest()) {
            return $this->renderCandidatesPartial($request, $project, $imageCandidateFetcher);
        }

        return $this->redirectToRoute('project_fetch_images', ['stringId' => $stringId]);
    }

    private function renderCandidatesPartial(Request $request, $project, ImageCandidateFetcher $imageCandidateFetcher): Response
    {
        $sourceUrl = $project->getIngestion()->getSourceUrl() ?? null;
        $postedHtml = is_string($request->request->get('page_html')) ? trim((string) $request->request->get('page_html')) : '';
        $fromPastedHtml = $postedHtml !== '';

        if ($fromPastedHtml) {
            $candidates = $imageCandidateFetcher->extractFromHtml($postedHtml, $sourceUrl);
        } else {
            $candidates = $sourceUrl ? $imageCandidateFetcher->fetch($sourceUrl) : [];
        }

        return $this->render('project_presentation/automation/images/_candidates.html.twig', [
            'project' => $project,
            'sourceUrl' => $sourceUrl,
            'candidates' => $candidates,
            'pageHtml' => $postedHtml,
            'fromPastedHtml' => $fromPastedHtml,
        ]);
    }

    private function denyInvalidCsrf(Request $request, string $stringId): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $this->addFlash('danger', 'Jeton CSRF invalide.');

        return $this->redirectToRoute('project_fetch_images', ['stringId' => $stringId]);
    }

}
