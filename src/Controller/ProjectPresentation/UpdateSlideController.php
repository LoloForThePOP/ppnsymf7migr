<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Enum\SlideType;
use App\Service\CacheThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectPresentation\ImageSlideType;
use App\Form\ProjectPresentation\VideoSlideType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UpdateSlideController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/slides/reorder',
        name: 'pp_reorder_slides'
    )]
    
    public function reorder(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
    ): Response
    {
        $this->denyAccessUnlessGranted('edit', $presentation);

        return $this->render('project_presentation/edit_show/slides/tiny_screens_reorder.html.twig', [
            'presentation' => $presentation,
        ]);
    }


    #[Route(
        '/projects/{stringId}/slides/update/{id_slide}',
        name: 'pp_update_slide'
    )]
    public function updateSlide(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $pp,
        #[MapEntity(mapping: ['id_slide' => 'id'])] Slide $slide,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $pp);
        $this->assertSlideOwnership($pp, $slide);

        return match ($slide->getType()) {
            SlideType::IMAGE         => $this->redirectToRoute('pp_update_image_slide', [
                'stringId' => $pp->getStringId(),
                'id_slide' => $slide->getId(),
            ]),
            SlideType::YOUTUBE_VIDEO => $this->redirectToRoute('pp_update_youtube_slide', [
                'stringId' => $pp->getStringId(),
                'id_slide' => $slide->getId(),
            ]),
            default => throw $this->createNotFoundException('Unknown slide type.'),
        };
    }


    #[Route(
        '/projects/{stringId}/slide/update-image/{id_slide}',
        name: 'pp_update_image_slide'
    )]
    public function updateImageSlide(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        #[MapEntity(mapping: ['id_slide' => 'id'])] Slide $slide,
        Request $request,
        EntityManagerInterface $manager,
        CacheThumbnailService $cacheThumbnailService
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);
        $this->assertSlideOwnership($presentation, $slide);

        $form = $this->createForm(ImageSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->flush();
            $cacheThumbnailService->updateThumbnail($presentation, true);

            $this->addFlash('success', "✅ Modification effectuée");

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId'  => $presentation->getStringId(),
                '_fragment' => 'slideshow-struct-container',
            ]);
        }

        return $this->render('project_presentation/edit_show/slides/update_image_slide.html.twig', [
            'stringId' => $presentation->getStringId(),
            'form'     => $form->createView(),
            'slide'    => $slide,
        ]);
    }


    #[Route(
        '/projects/{stringId}/slides/edit-youtube-video/{id_slide}',
        name: 'pp_update_youtube_slide'
    )]
    public function updateVideoSlide(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $pp,
        #[MapEntity(mapping: ['id_slide' => 'id'])] Slide $slide,
        Request $request,
        EntityManagerInterface $manager,
        CacheThumbnailService $cacheThumbnailService
    ): Response {
        $this->denyAccessUnlessGranted('edit', $pp);
        $this->assertSlideOwnership($pp, $slide);

        // reset image file field as we are dealing with an video slide

        $slide->setimageFile(null);


        $form = $this->createForm(VideoSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // to do: remove if the form is mapped correctly $slide->setYoutubeUrl($form->get('address')->getData());

            $manager->flush();
            $cacheThumbnailService->updateThumbnail($pp, true);

            $this->addFlash('success', "✅ Modification effectuée");

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId'  => $pp->getStringId(),
                '_fragment' => 'slideshow-struct-container',
            ]);
        }

        return $this->render('project_presentation/edit_show/slides/update_youtube_video_slide.html.twig', [
            'form' => $form->createView(),
            'stringId' => $pp->getStringId(),

        ]);
    }

    private function assertSlideOwnership(PPBase $presentation, Slide $slide): void
    {
        if ($slide->getProjectPresentation() !== $presentation) {
            throw $this->createNotFoundException('Slide introuvable.');
        }
    }
}
