<?php

namespace App\Controller\ProjectPresentation;

use App\Controller\SafeRefererRedirectTrait;
use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    use SafeRefererRedirectTrait;

    #[Route('/projects/{stringId}/settings/publish', name: 'pp_update_publish_status', methods: ['POST'])]
    public function updatePublishStatus(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('pp_publish_toggle' . $presentation->getStringId(), $token)) {
            $this->addFlash('danger', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        $shouldPublish = $request->request->has('published');
        $presentation->setIsPublished($shouldPublish);
        $em->flush();

        $this->addFlash(
            'success',
            $shouldPublish ? '✅ Présentation publiée.' : '✅ Présentation dépubliée.'
        );

        return $this->redirectToSafeReferer($request, 'edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]);
    }

    #[Route('/projects/{stringId}/settings/validation', name: 'pp_update_admin_validation', methods: ['POST'])]
    public function updateAdminValidation(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('pp_admin_validation_toggle' . $presentation->getStringId(), $token)) {
            $this->addFlash('danger', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        $shouldValidate = $request->request->has('validated');
        $presentation->setIsAdminValidated($shouldValidate);
        $em->flush();

        $this->addFlash(
            'success',
            $shouldValidate ? '✅ Présentation validée.' : '✅ Présentation invalidée.'
        );

        return $this->redirectToSafeReferer($request, 'edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
        ]);
    }

    #[Route('/projects/{stringId}/settings/admin-delete', name: 'pp_admin_delete_presentation', methods: ['POST'])]
    public function adminDeletePresentation(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('pp_admin_delete_presentation' . $presentation->getStringId(), $token)) {
            $this->addFlash('danger', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        $presentation->setIsPublished(false);
        $presentation->setIsDeleted(true);
        $em->flush();

        $this->addFlash('success', '✅ Présentation supprimée.');

        return $this->redirectToRoute('homepage');
    }


    #[Route('/projects/{stringId}/delete', name: 'pp_delete_presentation', methods: ['GET', 'POST'])]
    public function deletePresentation(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $presentation);

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('pp_delete_presentation' . $presentation->getStringId(), $token)) {
                $this->addFlash('danger', 'Le jeton CSRF est invalide.');

                return $this->redirectToRoute('pp_delete_presentation', [
                    'stringId' => $presentation->getStringId(),
                ]);
            }

            $confirmation = trim((string) $request->request->get('confirmation'));
            if (strtolower($confirmation) !== 'confirmer') {
                $this->addFlash('danger', 'Veuillez taper "confirmer" pour supprimer la présentation.');

                return $this->render('project_presentation/edit_show/delete_confirm.html.twig', [
                    'presentation' => $presentation,
                ]);
            }

            $presentation->setIsPublished(false);
            $presentation->setIsDeleted(true);
            $em->flush();

            $this->addFlash('success', '✅ Présentation supprimée.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('project_presentation/edit_show/delete_confirm.html.twig', [
            'presentation' => $presentation,
        ]);
    }
}
