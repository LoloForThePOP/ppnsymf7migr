<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Document;
use App\Form\ProjectPresentation\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateDocumentController extends AbstractController
{
    #[Route('/projects/documents/{id}', name: 'pp_update_document', methods: ['POST', 'GET'])]
    public function __invoke(
        Document $document,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $presentation = $document->getProjectPresentation();
        if (!$presentation) {
            throw new NotFoundHttpException('Document without project.');
        }
        $this->denyAccessUnlessGranted('edit', $presentation);
        $form = $this->createForm(DocumentType::class, $document, [
            'file_required' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em->flush();
                $this->addFlash('success', 'Document mis à jour.');
            } else {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $message = $errors ? implode(' ', $errors) : 'Le document n’a pas pu être mis à jour.';
                $this->addFlash('danger', $message);
            }

            $target = $this->generateUrl('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]) . '#documents';

            return $this->redirect($target);
        }

        return $this->render('project_presentation/edit_show/documents/update.html.twig', [
            'document' => $document,
            'presentation' => $presentation,
            'form' => $form->createView(),
            'stringId' => $presentation->getStringId(),
        ]);
    }
}
