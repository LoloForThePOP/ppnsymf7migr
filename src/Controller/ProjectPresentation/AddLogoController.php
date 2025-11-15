<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\ImageResizer;
use App\Service\CacheThumbnail;
use App\Service\FormHandlerService;
use App\Service\Form\GenericFormHandler;
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

        // Invalid → redirect back with message
        if (! $form->isSubmitted() || ! $form->isValid()) {
            $this->addFlash('error', 'Le formulaire est invalide.');
            return $this->redirectToRoute('show_presentation', [
                'stringId' => $presentation->getStringId(),
                '_fragment' => 'logo-struct-container'
            ]);
        }

        // Process with the generic handler
        // Here: we do *not* want to persist the form data object
        // because $presentation is already managed.
        $handler->handle($form, null, persist: false);

        // Flash message
        $this->addFlash('success', '✅ Logo mis à jour.');

        // Redirect to editor page (PRG pattern)
        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
            '_fragment' => 'logo-struct-container'
        ]);
    }
}
