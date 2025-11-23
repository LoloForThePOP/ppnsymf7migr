<?php

namespace App\Controller\ProjectPresentation;

use App\Repository\PPBaseRepository;
use App\Service\ImageCandidateFetcher;
use App\Service\ImageDownloader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImageFetchController extends AbstractController
{
    #[Route('/project/{stringId}/images', name: 'project_fetch_images', methods: ['GET', 'POST'])]
    public function __invoke(
        string $stringId,
        PPBaseRepository $repository,
        ImageCandidateFetcher $imageCandidateFetcher,
        Request $request
    ): Response {
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

        $viewData = [
            'project' => $project,
            'sourceUrl' => $sourceUrl,
            'candidates' => $candidates,
            'pageHtml' => $postedHtml,
            'fromPastedHtml' => $fromPastedHtml,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('project_presentation/images/_candidates.html.twig', $viewData);
        }

        return $this->render('project_presentation/images/fetch.html.twig', $viewData);
    }

    #[Route('/project/{stringId}/images/delete', name: 'project_delete_from_images', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $stringId, PPBaseRepository $repository): RedirectResponse
    {
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

        return $this->render('project_presentation/images/_candidates.html.twig', [
            'project' => $project,
            'sourceUrl' => $sourceUrl,
            'candidates' => $candidates,
            'pageHtml' => $postedHtml,
            'fromPastedHtml' => $fromPastedHtml,
        ]);
    }
}
