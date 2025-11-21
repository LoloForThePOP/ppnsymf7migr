<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Form\ProjectPresentation\CategoriesKeywordsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UpdateCategoriesKeywordsController extends AbstractController
{
    #[Route('/projects/{stringId}/categories-keywords', name: 'pp_update_categories_keywords', methods: ['POST'])]
    #[IsGranted('edit', subject: 'presentation')]
    public function __invoke(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(CategoriesKeywordsType::class, $presentation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégories et mots-clés mis à jour.');
        } elseif ($form->isSubmitted()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }
            $message = $messages ? implode(' ', $messages) : 'Impossible de mettre à jour les catégories/mots-clés.';
            $this->addFlash('danger', $message);
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]));
    }
}
