<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simple controller to serve whitelisted static pages.
 *
 * Add your static Twig files under templates/static/{slug}.html.twig,
 * then list them in PAGE_MAP below.
 */
class StaticPageController extends AbstractController
{
    private const PAGE_MAP = [
        'join_us'        => ['template' => 'static/about_us.html.twig', 'title' => 'Rejoignez-nous'],
        'short_manifesto' => ['template' => 'static/short_manifesto.html.twig', 'title' => 'Manifeste'],
        'credits'         => ['template' => 'static/credits.html.twig', 'title' => 'Crédits & remerciements'],
        'legal_notice'    => ['template' => 'static/legal_notice.html.twig', 'title' => 'Mentions légales'],
        'terms'           => ['template' => 'static/terms.html.twig', 'title' => 'Conditions d’utilisation'],
        'privacy'         => ['template' => 'static/privacy.html.twig', 'title' => 'Politique de confidentialité'],
        'submit_theme'    => ['template' => 'static/submit_theme.html.twig', 'title' => 'Proposer un thème'],
    ];

    #[Route('/pages/{slug}', name: 'static_page', methods: ['GET'])]
    public function __invoke(string $slug): Response
    {
        $page = self::PAGE_MAP[$slug] ?? null;
        if ($page === null) {
            throw new NotFoundHttpException();
        }

        return $this->render($page['template'], [
            'page_title' => $page['title'],
            'slug' => $slug,
            'contactEmail' => $this->getParameter('app.email.contact'),
        ]);
    }
}
