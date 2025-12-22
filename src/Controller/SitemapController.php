<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    private const STATIC_PAGES = [
        'join_us',
        'short_manifesto',
        'credits',
        'terms',
        'privacy',
        'submit_theme',
    ];

    private const STATIC_ROUTES = [
        'homepage',
        'index_articles',
        'contact_us',
        'app_registration',
    ];

    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function __invoke(
        Request $request,
        PPBaseRepository $ppBaseRepository,
        ArticleRepository $articleRepository,
    ): Response {
        $urls = [];
        $latestModified = null;

        foreach (self::STATIC_ROUTES as $route) {
            $urls[] = [
                'loc' => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        foreach (self::STATIC_PAGES as $slug) {
            $urls[] = [
                'loc' => $this->generateUrl('static_page', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ];
        }

        $presentationRows = $ppBaseRepository->createQueryBuilder('p')
            ->select('p.stringId AS stringId, p.updatedAt AS updatedAt, p.createdAt AS createdAt')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('published', true)
            ->setParameter('notDeleted', false)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        foreach ($presentationRows as $row) {
            $lastmod = $this->formatLastmod($row['updatedAt'] ?? $row['createdAt'] ?? null);
            $urls[] = [
                'loc' => $this->generateUrl('edit_show_project_presentation', [
                    'stringId' => $row['stringId'],
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $lastmod,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];

            $latestModified = $this->trackLatestModified($latestModified, $row['updatedAt'] ?? $row['createdAt'] ?? null);
        }

        $articleRows = $articleRepository->createQueryBuilder('a')
            ->select('a.slug AS slug, a.updatedAt AS updatedAt, a.createdAt AS createdAt')
            ->andWhere('a.isValidated = :validated')
            ->andWhere('a.slug IS NOT NULL')
            ->setParameter('validated', true)
            ->orderBy('a.updatedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        foreach ($articleRows as $row) {
            $lastmod = $this->formatLastmod($row['updatedAt'] ?? $row['createdAt'] ?? null);
            $urls[] = [
                'loc' => $this->generateUrl('show_article', [
                    'slug' => $row['slug'],
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $lastmod,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];

            $latestModified = $this->trackLatestModified($latestModified, $row['updatedAt'] ?? $row['createdAt'] ?? null);
        }

        $response = new Response(
            $this->renderView('sitemap.xml.twig', [
                'urls' => $urls,
            ])
        );

        $response->headers->set('Content-Type', 'application/xml');
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);

        if ($latestModified !== null) {
            $response->setLastModified($latestModified);
            if ($response->isNotModified($request)) {
                return $response;
            }
        }

        return $response;
    }

    private function formatLastmod(mixed $value): ?string
    {
        $date = $this->normalizeDate($value);
        return $date ? $date->format(\DateTimeInterface::ATOM) : null;
    }

    private function trackLatestModified(?\DateTimeInterface $current, mixed $value): ?\DateTimeInterface
    {
        $date = $this->normalizeDate($value);
        if ($date === null) {
            return $current;
        }

        if ($current === null || $date->getTimestamp() > $current->getTimestamp()) {
            return $date;
        }

        return $current;
    }

    private function normalizeDate(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
