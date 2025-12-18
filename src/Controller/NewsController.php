<?php

namespace App\Controller;

use App\Entity\News;
use App\Form\NewsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NewsController extends AbstractController
{
    #[Route('/news/edit/{id}', name: 'edit_news', methods: ['GET', 'POST'])]
    public function edit(News $news, Request $request, EntityManagerInterface $manager): Response
    {
        $presentation = $news->getProject();

        if ($presentation !== null) {
            $this->denyAccessUnlessGranted('edit', $presentation);
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $newsForm = $this->createForm(NewsType::class, $news);
        $newsForm->handleRequest($request);

        if ($newsForm->isSubmitted() && $newsForm->isValid()) {
            
            $manager->flush();

            $this->addFlash('success', '✅ News mise à jour.');

            return $this->redirectToRoute(
                'edit_show_project_presentation',

                [

                    'stringId' => $presentation?->getStringId(),
                    '_fragment' => 'news-struct-container'

                ]

            );

        }

        return $this->render('project_presentation/edit_show/news/edit.html.twig', [
            'newsForm' => $newsForm->createView(),
            'news' => $news,
            'ppStringId' => $presentation?->getStringId(),
        ]);

    }
}
