<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MiscSentryTestController extends AbstractController
{
    #[Route('/admin/misc/sentry-test', name: 'admin_misc_sentry_test', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_misc_sentry_test', $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_misc');
        }

        $env = (string) $this->getParameter('kernel.environment');
        if ($env !== 'prod') {
            $this->addFlash('warning', 'Test Sentry désactivé hors production.');

            return $this->redirectToRoute('admin_misc');
        }

        throw new \RuntimeException('Sentry test exception from /admin/misc/sentry-test');
    }
}

