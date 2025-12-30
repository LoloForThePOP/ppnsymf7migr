<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\LogoType;
use App\Service\CacheThumbnailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class UpdateLogoController extends AbstractController
{
    #[Route('/project/{stringId}/update/logo', name: 'update_project_presentation_logo')]
    #[IsGranted('edit', subject: 'presentation')]
    public function index(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
        CacheThumbnailService $cacheThumbnailService,
    ): Response
    {

        $form = $this->createForm(LogoType::class, $presentation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->flush();
            $cacheThumbnailService->updateThumbnail($presentation, true);
                       

            $this->addFlash(
                'success fade-out',
                "✅ Les modifications ont été enregistrées"
            );

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
                '_fragment' => 'logo-struct-container',
            ]);
        }


        return $this->render('project_presentation/edit_show/logo/update.html.twig', [
            'presentation' => $presentation,
            'stringId' => $presentation->getStringId(),
            'form' => $form->createView(),
        ]);






        return $this->render('update_logo/index.html.twig', [
            'controller_name' => 'UpdateLogoController',
        ]);

    }
}
