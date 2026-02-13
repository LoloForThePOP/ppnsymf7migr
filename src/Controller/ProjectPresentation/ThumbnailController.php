<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\CacheThumbnailService;
use App\Service\AssessPPScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectPresentation\ThumbnailType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Vich\UploaderBundle\Handler\UploadHandler;

final class ThumbnailController extends AbstractController
{
    #[Route('/projects/{stringId}/edit/thumbnail', name: 'pp_edit_thumbnail')]
    public function editThumbnail(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $manager,
        CacheThumbnailService $cacheThumbnail,
        AssessPPScoreService $scoreService,
        UploadHandler $uploadHandler,
    ): Response
    {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $form = $this->createForm(ThumbnailType::class, $presentation);

        $form->handleRequest($request);

        $removeCustomThumbnail = $request->request->get('remove_custom_thumbnail') === '1';
        if ($removeCustomThumbnail && $form->isSubmitted() && $form->isValid()) {
            if ($presentation->getCustomThumbnail()) {
                $uploadHandler->remove($presentation, 'customThumbnailFile');
                $scoreService->scoreUpdate($presentation);
                $manager->flush();
                $cacheThumbnail->updateThumbnail($presentation, true);

                $this->addFlash(
                    'success',
                    '✅ La vignette personnalisée a été supprimée.'
                );
            } else {
                $cacheThumbnail->updateThumbnail($presentation, true);
                $this->addFlash(
                    'info',
                    'Aucune vignette personnalisée à supprimer.'
                );
            }

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $scoreService->scoreUpdate($presentation);
            $manager->flush();

            $cacheThumbnail->updateThumbnail($presentation, true);

            $this->addFlash(
                'success',
                "✅ La vignette de votre présentation est modifiée !"
            );

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        return $this->render('project_presentation/edit_show/edit_custom_thumbnail.html.twig', [
            'presentation' => $presentation,
            'form' => $form->createView(),
        ]);
    }
}
