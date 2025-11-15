<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Form\ProjectPresentation\WebsiteType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\FormHandlerService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AddWebsiteController extends AbstractController
{


#[Route('/projects/{stringId}/component/website', name: 'pp_add_website', methods: ['POST'])]
public function addWebsite(
    #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
    Request $request,
    FormHandlerService $handler,
    //to do TreatItem $specificTreatments
): JsonResponse {
    $this->denyAccessUnlessGranted('edit', $presentation);

    $form = $this->createForm(WebsiteType::class);
    $form->handleRequest($request);

    if (! $form->isSubmitted() || ! $form->isValid()) {
        return new JsonResponse(['success' => false], 422);
    }

    $handler->handle($form, function ($website) use ($presentation) {
        $presentation->getOtherComponents()->addOtherComponentItem('websites', $website);
    }); 

    /*  $handler->handle($form, function ($website) use ($presentation, $specificTreatments) {
        $data = $specificTreatments->specificTreatments('websites', $website);
        $presentation->addOtherComponentItem('websites', $data);
    }); */

    return new JsonResponse([
        'success' => true,
        'message' => 'Ajout effectué',
    ]);

}





}
