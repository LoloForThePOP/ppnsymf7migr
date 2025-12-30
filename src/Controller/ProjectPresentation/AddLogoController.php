<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\CacheThumbnailService;
use App\Service\FormHandlerService;
use App\Service\AssessPPScoreService;
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
        CacheThumbnailService $cacheThumbnailService,
        AssessPPScoreService $scoreService,
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

        $scoreService->scoreUpdate($presentation);
        $handler->handle($form, null, persist: false);
        $cacheThumbnailService->updateThumbnail($presentation, true);


        // Redirect to editor page
        return $this->redirectToRoute('edit_show_project_presentation', [

            'stringId' => $presentation->getStringId(),
            '_fragment' => 'logo-struct-container'
            
        ]);

    }


}
