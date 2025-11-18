<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Enum\SlideType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\ImageSlideType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddImageSlideController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/add-image-slide',
        name: 'pp_add_image_slide',
        methods: ['POST']
    )]
    public function addImageSlide(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $manager,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $imageSlide = new Slide();
        $imageSlide->setType(SlideType::IMAGE);

        $form = $this->createForm(ImageSlideType::class, $imageSlide);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project_presentation/edit_show/origin.html.twig', [
                'presentation'      => $presentation,
                'addImageSlideForm' => $form->createView(),
            ]);
        }

        $imageSlide->setPosition($presentation->getSlides()->count());
        $presentation->addSlide($imageSlide);

        $manager->persist($imageSlide);
        $manager->flush();

        // TODO integrate AssessQuality/ImageResizer/CacheThumbnail services once ready.

        $this->addFlash('success', "✅ Image ajoutée");

        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId'  => $presentation->getStringId(),
            '_fragment' => 'slideshow-struct-container',
        ]);
    }
}
