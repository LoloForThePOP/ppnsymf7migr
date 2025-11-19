<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Form\ProjectPresentation\ImageSlideType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\LogoType;
use App\Form\ProjectPresentation\QuestionAnswerType;
use App\Form\ProjectPresentation\VideoSlideType;
use App\Form\ProjectPresentation\WebsiteType;
use App\Form\ProjectPresentation\TextDescriptionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EditShowController extends AbstractController
{
    #[Route(
        '/{stringId}/',
        name: 'edit_show_project_presentation',
        priority: -1
    )]
    #[IsGranted('view', subject: 'presentation')]
    public function EditShow(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {

        if($this->isGranted('edit', $presentation)){

            $addLogoForm = $this->createForm(LogoType::class, $presentation);
            $addWebsiteForm = $this->createForm(WebsiteType::class);
            $addQuestionAnswerForm = $this->createForm(QuestionAnswerType::class);
            $addImageSlideForm = $this->createForm(ImageSlideType::class);
            $addVideoSlideForm = $this->createForm(VideoSlideType::class);
            $textDescriptionForm = $this->createForm(TextDescriptionType::class, $presentation);
           
            return $this->render('project_presentation/edit_show/origin.html.twig', [
                'presentation' => $presentation,
                'addLogoForm' => $addLogoForm->createView(),
                'addWebsiteForm' => $addWebsiteForm->createView(),
                'addQuestionAnswerForm' => $addQuestionAnswerForm->createView(),
                'addImageSlideForm' => $addImageSlideForm->createView(),
                'addVideoSlideForm' => $addVideoSlideForm->createView(),
                'textDescriptionForm' => $textDescriptionForm->createView(),
            ]);


        }



        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
        ]);




    }


}
