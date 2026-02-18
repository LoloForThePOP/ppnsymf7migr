<?php

namespace App\Controller\Admin;

use App\Repository\PPBaseRepository;
use App\Service\CacheThumbnailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/thumbnails/refresh', name: 'admin_thumbnails_refresh', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_ADMIN')]
class ThumbnailRefreshController extends AbstractController
{
    public function __invoke(
        Request $request,
        PPBaseRepository $ppBaseRepository,
        CacheThumbnailService $cacheThumbnailService,
    ): Response {
        $offset = max(0, (int) ($request->request->get('offset', $request->query->get('offset', 0))));
        $limit = (int) ($request->request->get('limit', $request->query->get('limit', 200)));
        $limit = max(10, min(200, $limit));

        $total = (int) $ppBaseRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('notDeleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_thumbnails_refresh', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('admin_thumbnails_refresh');
            }
        }

        if ($request->isMethod('GET')) {
            $hasMore = $total > 0;

            return $this->render('admin/refresh_thumbnails.html.twig', [
                'processed' => 0,
                'total' => $total,
                'offset' => 0,
                'nextOffset' => 0,
                'limit' => $limit,
                'hasMore' => $hasMore,
                'started' => false,
            ]);
        }

        $presentations = $ppBaseRepository->createQueryBuilder('p')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $processed = 0;
        foreach ($presentations as $presentation) {
            $cacheThumbnailService->updateThumbnail($presentation, true);
            $processed++;
        }

        $nextOffset = $offset + $processed;
        $hasMore = $nextOffset < $total;

        return $this->render('admin/refresh_thumbnails.html.twig', [
            'processed' => $processed,
            'total' => $total,
            'offset' => $offset,
            'nextOffset' => $nextOffset,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'started' => true,
        ]);
    }
}
