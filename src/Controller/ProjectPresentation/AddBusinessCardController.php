<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectPresentation\BusinessCardType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;

final class AddBusinessCardController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/add-business-card',
        name: 'pp_add_business_card',
        methods: ['POST']
    )]
    public function addBusinessCard(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $businessCard = BusinessCardComponent::createNew();

        $form = $this->createForm(BusinessCardType::class, $businessCard, [
            'validation_groups' => ['input'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->render('project_presentation/edit_show/origin.html.twig', [
                'presentation' => $presentation,
                'addBusinessCardForm' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $presentation->getOtherComponents()->addComponent('business_cards', $businessCard);
            $em->flush();
        }

        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]);
    }
}
