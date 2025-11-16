<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\FormHandlerService;
use App\Form\ProjectPresentation\LogoType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddLogoController extends AbstractController
{


    #[Route(
        '/projects/{stringId}/add-logo',
        name: 'pp_add_logo',
        methods: ['POST']
    )]
    public function addLogo(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        FormHandlerService $handler,
    ): Response {

        $this->denyAccessUnlessGranted('edit', $presentation);

        // Create the form bound to the $presentation object
        $form = $this->createForm(LogoType::class, $presentation);
        $form->handleRequest($request);

        // Invalid form
        if (! $form->isSubmitted() || ! $form->isValid()) {

            $this->addFlash('error', 'Le formulaire est invalide.');

            return $this->redirectToRoute('edit_show_project_presentation', [

                'stringId' => $presentation->getStringId(),
                '_fragment' => 'logo-struct-container'

            ]);

        }

        // to do: resize image
        // to do: cache thumbnail

        $handler->handle($form, null, persist: false);


        // Redirect to editor page
        return $this->redirectToRoute('edit_show_project_presentation', [

            'stringId' => $presentation->getStringId(),
            '_fragment' => 'logo-struct-container'
            
        ]);

    }


}
