<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Form\ProjectPresentation\BusinessCardType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateBusinessCardController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/business-cards/{id}',
        name: 'pp_update_business_card',
        methods: ['GET', 'POST']
    )]
    public function __invoke(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        string $id,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $item = $presentation->getOtherComponents()->getItem('business_cards', $id);
        if ($item === null) {
            throw new NotFoundHttpException('Carte de visite introuvable.');
        }

        $component = BusinessCardComponent::fromArray($item);
        $form = $this->createForm(BusinessCardType::class, $component, [
            'validation_groups' => ['input'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $presentation->getOtherComponents()->updateComponent('business_cards', $component);
                $em->flush();
                $this->addFlash('success', 'Carte de visite mise à jour.');
            } else {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if ($errors) {
                    $this->addFlash('danger', implode(' ', $errors));
                }
            }

            $target = $this->generateUrl('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]) . '#businessCards-struct-container';

            return $this->redirect($target);
        }

        return $this->render('project_presentation/edit_show/business_cards/update.html.twig', [
            'presentation' => $presentation,
            'stringId' => $presentation->getStringId(),
            'form' => $form->createView(),
        ]);
    }
}
