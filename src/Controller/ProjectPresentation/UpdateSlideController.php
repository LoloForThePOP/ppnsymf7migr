<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Entity\Slide;
use App\Form\ProjectPresentation\ImageSlideType;
use App\Form\ProjectPresentation\VideoSlideType;
use App\Repository\SlideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UpdateSlideController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/slides/reorder',
        name: 'pp_reorder_slides'
    )]
    public function reorder(PPBase $presentation): Response
    {
        $this->denyAccessUnlessGranted('edit', $presentation);

        return $this->render('project_presentation/show_edit/slides/tiny_screens_reorder.html.twig', [
            'presentation' => $presentation,
        ]);
    }


    #[Route(
        '/projects/{stringId}/slides/update/{id_slide}',
        name: 'pp_update_slide'
    )]
    public function updateSlide(
        PPBase $pp,
        int $id_slide,
        SlideRepository $repo
    ): Response {
        $this->denyAccessUnlessGranted('edit', $pp);

        $slide = $repo->find($id_slide);

        if (!$slide instanceof Slide) {
            throw $this->createNotFoundException('Slide not found.');
        }

        return match ($slide->getType()) {
            'image'         => $this->redirectToRoute('pp_update_image_slide', [
                'stringId' => $pp->getStringId(),
                'id_slide' => $id_slide,
            ]),
            'youtube_video' => $this->redirectToRoute('pp_update_youtube_slide', [
                'stringId' => $pp->getStringId(),
                'id_slide' => $id_slide,
            ]),
            default => throw $this->createNotFoundException('Unknown slide type.'),
        };
    }


    #[Route(
        '/projects/{stringId}/slide/update-image/{id_slide}',
        name: 'pp_update_image_slide'
    )]
    public function updateImageSlide(
        PPBase $presentation,
        int $id_slide,
        SlideRepository $repo,
        Request $request,
        EntityManagerInterface $manager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $slide = $repo->find($id_slide);
        if (!$slide instanceof Slide) {
            throw $this->createNotFoundException('Slide not found.');
        }

        $form = $this->createForm(ImageSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //to do: resize image
            //to do: cache thumbnail

            $manager->flush();

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
        PPBase $pp,
        int $id_slide,
        SlideRepository $repo,
        Request $request,
        EntityManagerInterface $manager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $pp);

        $slide = $repo->find($id_slide);
        if (!$slide instanceof Slide) {
            throw $this->createNotFoundException('Slide not found.');
        }

        // reset image file field as we are dealing with an video slide

        $slide->setimageFile(null);


        $form = $this->createForm(VideoSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // to do: remove if the form is mapped correctly $slide->setYoutubeUrl($form->get('address')->getData());

            $manager->flush();

            $this->addFlash('success', "✅ Modification effectuée");

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId'  => $pp->getStringId(),
                '_fragment' => 'slideshow-struct-container',
            ]);
        }

        return $this->render('project_presentation/edit_show/slides/update_youtube_video_slide.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
