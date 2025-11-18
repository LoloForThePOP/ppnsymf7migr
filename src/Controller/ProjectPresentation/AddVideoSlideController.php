<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Enum\SlideType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectPresentation\ImageSlideType;
use App\Form\ProjectPresentation\VideoSlideType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddVideoSlideController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/add-video-slide',
        name: 'pp_add_video_slide',
        methods: ['POST']
    )]
    public function addVideoSlide(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $manager,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $videoSlide = new Slide();
        $videoSlide->setType(SlideType::YOUTUBE_VIDEO);

        $form = $this->createForm(VideoSlideType::class, $videoSlide);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project_presentation/edit_show/origin.html.twig', [
                'presentation'      => $presentation,
                'addVideoSlideForm' => $form->createView(),
            ]);
        }

        $videoSlide->setPosition($presentation->getSlides()->count());
        $presentation->addSlide($videoSlide);

        $manager->persist($videoSlide);
        $manager->flush();

        // TODO integrate AssessQuality/CacheThumbnail services once ready.

        $this->addFlash('success', "✅ Image ajoutée");

        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId'  => $presentation->getStringId(),
            '_fragment' => 'slideshow-struct-container',
        ]);
    }
}
