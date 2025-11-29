<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Document;
use App\Entity\PPBase;
use App\Form\ProjectPresentation\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AddDocumentController extends AbstractController
{
    #[Route('/projects/{stringId}/documents', name: 'pp_add_document', methods: ['POST'])]
    #[IsGranted('edit', subject: 'presentation')]
    public function __invoke(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setProjectPresentation($presentation);
            // position at end
            $maxPos = array_reduce(
                $presentation->getDocuments()->toArray(),
                fn($carry, $doc) => max($carry, (int) ($doc->getPosition() ?? 0)),
                0
            );
            $document->setPosition($maxPos + 1);

            $em->persist($document);
            $em->flush();

            $this->addFlash('success', 'Document ajouté.');
        } elseif ($form->isSubmitted()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $message = $errors ? implode(' ', $errors) : 'Le document n’a pas pu être ajouté.';
            $this->addFlash('danger', $message);
        }

        $target = $this->generateUrl('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]);

        return $this->redirect($target . '#documents');
    }
}
