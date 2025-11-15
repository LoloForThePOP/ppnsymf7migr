<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\LogoType;

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
            $addLogoForm->handleRequest($request);
            
            if ($addLogoForm->isSubmitted() && $addLogoForm->isValid()) {

                $em->flush();

                // to do: image resize
                // to do: cache thumbnail

                $this->addFlash(
                    'success',
                    "✅ Modification Effectuée"
                );

                return $this->redirectToRoute(
                    'edit_show_project_presentation',
                    [

                        'stringId' => $presentation->getStringId(),

                    ]
                );

            }


            return $this->render('project_presentation/edit_show/origin.html.twig', [
                'presentation' => $presentation,
                'addLogoForm' => $addLogoForm->createView(),
            ]);


        }



        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
        ]);




    }


}