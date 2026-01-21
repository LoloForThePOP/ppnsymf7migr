<?php

namespace App\Controller\Admin;

use App\Entity\UluleProjectCatalog;
use App\Message\UluleImportTickMessage;
use App\Repository\UluleProjectCatalogRepository;
use App\Security\Voter\ScraperAccessVoter;
use App\Service\UluleCatalogRefresher;
use App\Service\UluleQueueStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
#[Route('/admin/ulule/queue')]
final class UluleQueueController extends AbstractController
{
    #[Route('/start', name: 'admin_ulule_queue_start', methods: ['POST'])]
    public function start(
        Request $request,
        UluleCatalogRefresher $refresher,
        UluleQueueStateService $stateService,
        MessageBusInterface $bus
    ): Response {
        $filters = $this->readFilters($request);
        $batchSize = max(0, (int) $request->request->get('batch_size', 0));

        $refreshSummary = $refresher->refreshCatalog(
            $filters['lang'],
            $filters['country'],
            $filters['status'],
            $filters['sort'],
            $filters['page_start'],
            $filters['page_count'],
            $filters['extra_query']
        );
        foreach ($refreshSummary['error_messages'] ?? [] as $message) {
            $this->addFlash('danger', $message);
        }

        $runId = bin2hex(random_bytes(8));
        $stateService->writeState([
            'filters' => $filters,
            'queue' => [
                'paused' => false,
                'running' => true,
                'remaining' => $batchSize > 0 ? $batchSize : null,
                'run_id' => $runId,
                'current_id' => null,
            ],
        ]);

        $bus->dispatch(new UluleImportTickMessage($runId));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_ulule_catalog'));
    }

    #[Route('/pause', name: 'admin_ulule_queue_pause', methods: ['POST'])]
    public function pause(Request $request, UluleQueueStateService $stateService): Response
    {
        $stateService->writeState([
            'queue' => [
                'paused' => true,
                'running' => false,
            ],
        ]);

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_ulule_catalog'));
    }

    #[Route('/resume', name: 'admin_ulule_queue_resume', methods: ['POST'])]
    public function resume(
        Request $request,
        UluleQueueStateService $stateService,
        MessageBusInterface $bus,
        UluleProjectCatalogRepository $catalogRepository
    ): Response {
        $state = $stateService->readState();
        $queue = $state['queue'];
        $filters = $state['filters'];

        $queue['paused'] = false;
        $queue['running'] = $this->hasQueueable($catalogRepository, $filters);
        $queue['current_id'] = null;

        if ($queue['run_id'] === null || $queue['run_id'] === '') {
            $queue['run_id'] = bin2hex(random_bytes(8));
        }

        $stateService->writeState(['queue' => $queue]);

        if ($queue['running']) {
            $bus->dispatch(new UluleImportTickMessage($queue['run_id']));
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_ulule_catalog'));
    }

    #[Route('/status', name: 'admin_ulule_queue_status', methods: ['GET'])]
    public function status(
        Request $request,
        UluleQueueStateService $stateService,
        UluleProjectCatalogRepository $catalogRepository
    ): JsonResponse {
        $state = $stateService->readState();
        $filters = $this->readFilters($request, $state['filters']);

        $idsParam = trim((string) $request->query->get('ids', ''));
        $ids = [];
        if ($idsParam !== '') {
            foreach (explode(',', $idsParam) as $id) {
                $value = (int) trim($id);
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
            $ids = array_values(array_unique($ids));
        }

        if ($ids !== []) {
            $items = $catalogRepository->findBy(['ululeId' => $ids]);
        } else {
            $items = $this->loadItems($catalogRepository, $filters['status_filter']);
        }

        $eligibleOnly = $filters['eligible_only'];
        $payload = [];
        foreach (array_slice($items, 0, 200) as $item) {
            if (!$item instanceof UluleProjectCatalog) {
                continue;
            }
            $eligible = $this->isEligible($item, $filters);
            if ($ids === [] && $eligibleOnly && !$eligible) {
                continue;
            }
            $payload[] = [
                'id' => $item->getUluleId(),
                'import_status' => $item->getImportStatus(),
                'import_status_comment' => $item->getImportStatusComment(),
                'last_error' => $item->getLastError(),
                'imported_string_id' => $item->getImportedStringId(),
                'eligible' => $eligible,
                'created_url' => $item->getImportedStringId()
                    ? $this->generateUrl('edit_show_project_presentation', ['stringId' => $item->getImportedStringId()], UrlGeneratorInterface::ABSOLUTE_PATH)
                    : null,
            ];
        }

        return new JsonResponse([
            'queue' => $state['queue'],
            'summary' => $catalogRepository->getStatusCounts(),
            'items' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(Request $request, array $defaults = []): array
    {
        $source = $request->isMethod('POST') ? $request->request : $request->query;

        $filters = [
            'lang' => trim((string) $source->get('lang', $defaults['lang'] ?? 'fr')),
            'country' => trim((string) $source->get('country', $defaults['country'] ?? 'FR')),
            'status' => trim((string) $source->get('status', $defaults['status'] ?? 'currently')),
            'sort' => trim((string) $source->get('sort', $defaults['sort'] ?? 'new')),
            'page_start' => max(1, (int) $source->get('page_start', $defaults['page_start'] ?? 1)),
            'page_count' => max(1, (int) $source->get('page_count', $defaults['page_count'] ?? 10)),
            'min_description_length' => max(0, (int) $source->get('min_description_length', $defaults['min_description_length'] ?? 500)),
            'exclude_funded' => $source->has('exclude_funded')
                ? (bool) $source->get('exclude_funded')
                : (bool) ($defaults['exclude_funded'] ?? false),
            'include_video' => $source->has('include_video')
                ? (bool) $source->get('include_video')
                : (bool) ($defaults['include_video'] ?? true),
            'include_secondary_images' => $source->has('include_secondary_images')
                ? (bool) $source->get('include_secondary_images')
                : (bool) ($defaults['include_secondary_images'] ?? true),
            'extra_query' => trim((string) $source->get('extra_query', $defaults['extra_query'] ?? '')),
            'prompt_extra' => trim((string) $source->get('prompt_extra', $defaults['prompt_extra'] ?? '')),
            'eligible_only' => $source->has('eligible_only')
                ? (bool) $source->get('eligible_only')
                : (bool) ($defaults['eligible_only'] ?? true),
            'status_filter' => trim((string) $source->get('status_filter', $defaults['status_filter'] ?? 'pending')),
        ];

        return $filters;
    }

    /**
     * @return UluleProjectCatalog[]
     */
    private function loadItems(UluleProjectCatalogRepository $repository, string $statusFilter): array
    {
        $qb = $repository->createQueryBuilder('u')
            ->orderBy('u.lastSeenAt', 'DESC')
            ->addOrderBy('u.ululeId', 'DESC');

        if ($statusFilter !== 'all') {
            $qb->andWhere('u.importStatus = :status')
                ->setParameter('status', $statusFilter);
        }

        return $qb->getQuery()->getResult();
    }

    private function hasQueueable(UluleProjectCatalogRepository $repository, array $filters): bool
    {
        $items = $this->loadItems($repository, $filters['status_filter'] ?? UluleProjectCatalog::STATUS_PENDING);
        foreach ($items as $item) {
            if ($item instanceof UluleProjectCatalog && $this->isEligible($item, $filters)) {
                return true;
            }
        }

        return false;
    }

    private function isEligible(UluleProjectCatalog $item, array $filters): bool
    {
        if (($filters['exclude_funded'] ?? false) && $item->getGoalRaised()) {
            return false;
        }
        if ($item->getIsCancelled()) {
            return false;
        }
        if ($item->getIsOnline() === false) {
            return false;
        }
        $length = $item->getDescriptionLength();
        if ($length === null) {
            return false;
        }
        $minDescriptionLength = (int) ($filters['min_description_length'] ?? 0);
        if ($minDescriptionLength > 0 && $length < $minDescriptionLength) {
            return false;
        }

        return true;
    }
}
