<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\News;
use App\Entity\PPBase;
use App\Form\NewsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CreateNewsController extends AbstractController
{
    #[Route(
        '/projects/{stringId}/news',
        name: 'pp_create_news',
        methods: ['POST']
    )]
    #[IsGranted('edit', subject: 'presentation')]
    public function __invoke(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $news = new News();
        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        $submittedPresentationId = (int) $form->get('presentationId')->getData();
        if ($submittedPresentationId !== 0 && $submittedPresentationId !== $presentation->getId()) {
            throw $this->createAccessDeniedException('Présentation cible invalide.');
        }

        if (!$form->isSubmitted() || !$form->isValid()) {
            if ($form->isSubmitted()) {
                $messages = [];
                foreach ($form->getErrors(true) as $error) {
                    $messages[] = $error->getMessage();
                }
                $this->addFlash('danger', $messages ? implode(' ', $messages) : "La news n'a pas pu être créée.");
            }

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
                '_fragment' => 'news-struct-container',
            ]);
        }

        $presentation->addNews($news);

        $em->persist($news);
        $em->flush();

        $this->addFlash('success', '✅ Votre actualité a été publiée.');

        return $this->redirectToRoute('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
            '_fragment' => 'news-struct-container',
        ]);
    }
}
