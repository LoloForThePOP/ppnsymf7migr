<?php

namespace App\MessageHandler;

use App\Entity\UluleProjectCatalog;
use App\Message\UluleImportTickMessage;
use App\Repository\UluleProjectCatalogRepository;
use App\Service\UluleImportService;
use App\Service\UluleQueueStateService;
use App\Service\WorkerHeartbeatService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class UluleImportTickHandler
{
    public function __construct(
        private readonly UluleQueueStateService $queueStateService,
        private readonly UluleProjectCatalogRepository $catalogRepository,
        private readonly UluleImportService $importService,
        private readonly WorkerHeartbeatService $workerHeartbeat,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(UluleImportTickMessage $message): void
    {
        $this->workerHeartbeat->touch('ulule_import');
        $state = $this->queueStateService->readState();
        $queue = $state['queue'];
        $filters = $state['filters'];

        if ($queue['run_id'] !== $message->getRunId()) {
            return;
        }
        if ($queue['paused']) {
            $queue['running'] = false;
            $queue['current_id'] = null;
            $this->queueStateService->writeState(['queue' => $queue]);
            return;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            return;
        }

        try {
            $next = $this->findNextQueueable($filters);
            if ($next === null) {
                $queue['running'] = false;
                $queue['current_id'] = null;
                $this->queueStateService->writeState(['queue' => $queue]);
                return;
            }

            $queue['current_id'] = $next->getUluleId();
            $this->queueStateService->writeState(['queue' => $queue]);

            $this->importService->importProject($next->getUluleId(), $filters);

            $state = $this->queueStateService->readState();
            $queue = $state['queue'];
            if ($queue['paused']) {
                $queue['running'] = false;
                $queue['current_id'] = null;
                $this->queueStateService->writeState(['queue' => $queue]);
                return;
            }

            if (is_int($queue['remaining'])) {
                $queue['remaining'] = max(0, $queue['remaining'] - 1);
            }

            $queue['last_processed_id'] = $next->getUluleId();
            $queue['current_id'] = null;
            $queueable = $this->hasQueueable($filters);
            $queue['running'] = $queueable && (!is_int($queue['remaining']) || $queue['remaining'] > 0);
            $this->queueStateService->writeState(['queue' => $queue]);

            if ($queue['running']) {
                $this->bus->dispatch(new UluleImportTickMessage($queue['run_id']));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function findNextQueueable(array $filters): ?UluleProjectCatalog
    {
        $statusFilter = $filters['status_filter'] ?? UluleProjectCatalog::STATUS_PENDING;
        if (!in_array($statusFilter, [UluleProjectCatalog::STATUS_PENDING, UluleProjectCatalog::STATUS_FAILED], true)) {
            $statusFilter = UluleProjectCatalog::STATUS_PENDING;
        }

        $qb = $this->catalogRepository->createQueryBuilder('u')
            ->orderBy('u.lastSeenAt', 'DESC')
            ->addOrderBy('u.ululeId', 'DESC')
            ->andWhere('u.importStatus = :status')
            ->setParameter('status', $statusFilter);

        $items = $qb->getQuery()->getResult();
        foreach ($items as $item) {
            if (!$item instanceof UluleProjectCatalog) {
                continue;
            }
            if (!$this->isEligible($item, $filters)) {
                if (!($filters['eligible_only'] ?? true)) {
                    return $item;
                }
                continue;
            }

            return $item;
        }

        return null;
    }

    private function hasQueueable(array $filters): bool
    {
        return $this->findNextQueueable($filters) !== null;
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

    private function acquireLock(): mixed
    {
        $path = $this->queueStateService->resolveLockPath();
        $handle = fopen($path, 'c');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }
}
