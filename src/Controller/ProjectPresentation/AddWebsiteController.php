<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\FormHandlerService;
use App\Service\WebsiteProcessingService;
use App\Form\ProjectPresentation\WebsiteType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddWebsiteController extends AbstractController
{

#[Route(
    '/projects/{stringId}/add-website',
    name: 'pp_add_website',
    methods: ['POST']
)]
public function addLogo(
    #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
    Request $request,
    EntityManager $em,
    WebsiteProcessingService $websiteProcessingService,
): Response {

    $this->denyAccessUnlessGranted('edit', $presentation);

    // IMPORTANT: bind the form to a WebsiteComponent object
    $websiteComponent = WebsiteComponent::createNew('', '', 0);

    $form = $this->createForm(WebsiteType::class, $websiteComponent, [
        'validation_groups' => ['input'], // ensures only title/url are validated
    ]);
    $form->handleRequest($request);

    if (!$form->isSubmitted() || !$form->isValid()) {

        return $this->redirectToRoute('esit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
            '_fragment' => 'websites-struct-container'
        ]);
    }

    // Compute next position
    $position = count($presentation->getOtherComponents()->getOC('websites'));
    $websiteComponent->setPosition($position);

    // Normalize URL, assign icon, favicon, updatedAt
    $websiteProcessingService->process($websiteComponent);

    // Store in embeddable
    $presentation->getOtherComponents()->addOtherComponentItem(
        'websites',
        $websiteComponent->toArray()
    );
    

    $em->flush();

    return $this->redirectToRoute('edit_show_project_presentation', [
        'stringId' => $presentation->getStringId(),
    ]);
    
}





}
