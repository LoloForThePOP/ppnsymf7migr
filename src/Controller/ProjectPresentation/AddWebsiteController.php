<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\WebsiteProcessingService;
use App\Form\ProjectPresentation\WebsiteType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddWebsiteController extends AbstractController
{

#[Route(
    '/projects/{stringId}/add-website',
    name: 'pp_add_website',
    methods: ['POST']
)]
public function addWebsite(
    #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
    Request $request,
    EntityManagerInterface $em,
    WebsiteProcessingService $websiteProcessingService,
): Response {

    $this->denyAccessUnlessGranted('edit', $presentation);

    // binding the form to a WebsiteComponent object
    $website = WebsiteComponent::createNew('', '');

    $form = $this->createForm(WebsiteType::class, $website, [
        'validation_groups' => ['input'], // ensures only title/url are validated
    ]);
    
    $form->handleRequest($request);

    // invalid form resend it
    if ($form->isSubmitted() && !$form->isValid()) {
        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
            'addWebsiteForm' => $form->createView(),
        ]);
    }


    if ($form->isSubmitted() && $form->isValid()) {

        $websiteProcessingService->process($website);

        $presentation->getOtherComponents()->addComponent('websites', $website);

        $em->flush();

    }

    return $this->redirectToRoute('edit_show_project_presentation', [
        'stringId' => $presentation->getStringId(),
    ]);
    
}





}
