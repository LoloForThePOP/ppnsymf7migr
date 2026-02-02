<?php

namespace App\Controller\Admin;

use App\Service\ScraperUserResolver;
use App\Service\UrlHarvestListService;
use App\Service\UrlHarvestRunner;
use App\Service\UrlHarvestResultStore;
use App\Service\WorkerHeartbeatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;
use App\Message\UrlHarvestTickMessage;

#[Route('/admin/project/harvest-urls')]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
final class UrlHarvestController extends AbstractController
{
    #[Route('', name: 'admin_project_harvest_urls', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        ScraperUserResolver $scraperUserResolver,
        UrlHarvestListService $listService,
        UrlHarvestRunner $runner,
        UrlHarvestResultStore $resultStore,
        MessageBusInterface $bus,
        WorkerHeartbeatService $workerHeartbeat,
        string $appNormalizeHtmlPromptPath,
        string $appScraperModel
    ): Response {
        $urlsText = trim((string) $request->request->get('urls', ''));
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        $persist = (bool) $request->request->get('persist', false);
        $results = [];

        $action = (string) $request->request->get('action', '');
        $selectedSource = trim((string) $request->query->get('source', ''));
        if ($selectedSource === '') {
            $selectedSource = trim((string) $request->request->get('source', ''));
        }

        $sources = $listService->listSources();
        $sourcePrompt = $selectedSource !== '' ? $listService->readPrompt($selectedSource) : '';
        $queueState = $selectedSource !== '' ? $listService->readQueueState($selectedSource) : $this->defaultQueueState();
        $sourceEntries = [];
        $sourceError = null;
        $sourceSummary = null;
        $sourceResults = [];
        $batchSize = (int) $request->request->get('batch_size', 0);
        $batchSize = max(0, min(500, $batchSize));
        $persistSourceParam = $request->request->get('persist_source');
        if ($persistSourceParam === null) {
            $persistSource = $selectedSource !== '';
        } else {
            $persistSource = filter_var($persistSourceParam, FILTER_VALIDATE_BOOLEAN);
        }

        if ($selectedSource !== '') {
            $loaded = $listService->loadEntries($selectedSource);
            $entries = $this->normalizeStaleEntries(
                $selectedSource,
                $loaded['entries'],
                $queueState,
                $listService,
                $workerHeartbeat->getStatus()['active'] ?? false
            );
            $sourceEntries = $this->orderEntriesForDisplay($entries);
            $sourceError = $loaded['error'];
            $sourceSummary = $this->summarizeEntries($sourceEntries);
            $sourceResults = $this->loadLastResults($resultStore, $selectedSource, $sourceEntries);
            foreach ($sourceEntries as &$entry) {
                if (!isset($entry['url']) || !is_string($entry['url'])) {
                    continue;
                }
                $entry['result_key'] = $resultStore->getPublicKey($entry['url']);
                $entry['has_result'] = $resultStore->hasResult($selectedSource, $entry['url']);
            }
            unset($entry);
        }

        if ($selectedSource !== '' && $request->isMethod('POST') && $action !== '') {
            $sourcePrompt = trim((string) $request->request->get('source_prompt_extra', $sourcePrompt));

            if (in_array($action, ['save_source_prompt', 'run_source'], true)) {
                try {
                    $listService->writePrompt($selectedSource, $sourcePrompt);
                } catch (\Throwable $e) {
                    $sourceError = $e->getMessage();
                }
            }

            if ($action === 'run_source' && $sourceError === null) {
                $queueState['paused'] = false;
                $queueState['running'] = true;
                $queueState['persist'] = $persistSource;
                $queueState['remaining'] = $batchSize > 0 ? $batchSize : null;
                $listService->writeQueueState($selectedSource, $queueState);
                // Always dispatch one tick on manual run to recover from stale "running" states.
                $bus->dispatch(new UrlHarvestTickMessage($selectedSource));
            }

            if ($action === 'pause_source' && $sourceError === null) {
                $queueState['paused'] = true;
                $queueState['running'] = false;
                $listService->writeQueueState($selectedSource, $queueState);
            }

            if ($action === 'resume_source' && $sourceError === null) {
                $queueState['paused'] = false;
                if ($this->hasQueueableEntries($sourceEntries)) {
                    $queueState['running'] = true;
                    $listService->writeQueueState($selectedSource, $queueState);
                    $bus->dispatch(new UrlHarvestTickMessage($selectedSource));
                } else {
                    $queueState['running'] = false;
                    $listService->writeQueueState($selectedSource, $queueState);
                }
            }
        }

        if ($urlsText !== '' && $request->isMethod('POST') && $action === 'manual') {
            $creator = null;
            if ($persist) {
                $creator = $scraperUserResolver->resolve();
                if (!$creator) {
                    $this->addFlash('warning', sprintf(
                        'Compte "%s" introuvable ou multiple. Persistance désactivée.',
                        $scraperUserResolver->getRole()
                    ));
                    $persist = false;
                }
            }

            $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $urlsText))));
            $urls = array_slice($urls, 0, 10); // guardrail

            $prompt = file_get_contents($appNormalizeHtmlPromptPath);
            if ($prompt === false) {
                $results[] = ['url' => null, 'error' => 'Prompt introuvable.'];
            } else {
                if ($promptExtra !== '') {
                    $prompt = rtrim($prompt) . "\n\n" . $promptExtra;
                }

                foreach ($urls as $url) {
                    $results[] = $runner->run(
                        $url,
                        $persist,
                        $prompt,
                        $appScraperModel,
                        $creator,
                        $this->defaultPayloadPolicy(),
                        false
                    );
                }
            }
        }

        return $this->render('admin/project_harvest_urls.html.twig', [
            'urls' => $urlsText,
            'promptExtra' => $promptExtra,
            'persist' => $persist,
            'results' => $results,
            'sources' => $sources,
            'selectedSource' => $selectedSource,
            'sourcePrompt' => $sourcePrompt,
            'sourceEntries' => $sourceEntries,
            'sourceSummary' => $sourceSummary,
            'sourceError' => $sourceError,
            'sourceResults' => $sourceResults,
            'sourceBatchSize' => $batchSize,
            'persistSource' => $persistSource,
            'queueState' => $queueState,
            'workerStatus' => $workerHeartbeat->getStatus(),
            'workerCommand' => 'php bin/console messenger:consume async -vv',
        ]);
    }

    #[Route('/status', name: 'admin_project_harvest_urls_status', methods: ['GET', 'POST'])]
    #[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
    public function status(
        Request $request,
        UrlHarvestListService $listService,
        UrlHarvestResultStore $resultStore,
        WorkerHeartbeatService $workerHeartbeat
    ): JsonResponse
    {
        $source = trim((string) $request->query->get('source', ''));
        if ($source === '') {
            return new JsonResponse(['error' => 'Source manquante.'], Response::HTTP_BAD_REQUEST);
        }

        $loaded = $listService->loadEntries($source);
        if ($loaded['error'] !== null) {
            return new JsonResponse(['error' => $loaded['error']], Response::HTTP_BAD_REQUEST);
        }

        $queueState = $listService->readQueueState($source);
        $entries = $this->normalizeStaleEntries(
            $source,
            $loaded['entries'],
            $queueState,
            $listService,
            $workerHeartbeat->getStatus()['active'] ?? false
        );
        $entries = $this->orderEntriesForDisplay($entries);
        $requestedUrls = $request->request->all('urls');
        if ($requestedUrls === [] && $request->query->has('urls')) {
            $requestedUrls = $request->query->all('urls');
        }

        if (is_string($requestedUrls)) {
            $requestedUrls = array_filter(array_map('trim', explode(',', $requestedUrls)));
        }

        if (is_array($requestedUrls) && $requestedUrls !== []) {
            $requestedUrls = array_values(array_filter($requestedUrls, fn($value) => is_string($value) && $value !== ''));
            $entryByUrl = [];
            foreach ($entries as $entry) {
                $entryByUrl[$entry['url']] = $entry;
            }
            $entries = [];
            foreach ($requestedUrls as $url) {
                if (isset($entryByUrl[$url])) {
                    $entries[] = $entryByUrl[$url];
                }
            }
        }

        $entries = array_slice($entries, 0, 200);
        foreach ($entries as &$entry) {
            $entry['result_key'] = $resultStore->getPublicKey($entry['url']);
            $entry['has_result'] = $resultStore->hasResult($source, $entry['url']);
        }
        unset($entry);

        return new JsonResponse([
            'entries' => $entries,
            'summary' => $this->summarizeEntries($loaded['entries']),
            'queue' => $queueState,
            'worker' => $workerHeartbeat->getStatus(),
        ]);
    }

    #[Route('/result', name: 'admin_project_harvest_urls_result', methods: ['GET'])]
    #[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
    public function result(Request $request, UrlHarvestResultStore $resultStore): JsonResponse
    {
        $source = trim((string) $request->query->get('source', ''));
        $key = trim((string) $request->query->get('key', ''));
        if ($source === '' || $key === '') {
            return new JsonResponse(['error' => 'Paramètres manquants.'], Response::HTTP_BAD_REQUEST);
        }

        $data = $resultStore->loadByKey($source, $key);
        if ($data === null) {
            return new JsonResponse(['error' => 'Résultat introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }

    /**
     * @param array<int, array{url:string,status:string,last_run_at:string,error:string,notes:string}> $entries
     * @return array<string, int>
     */
    private function summarizeEntries(array $entries): array
    {
        $summary = [
            'total' => 0,
            'pending' => 0,
            'queued' => 0,
            'processing' => 0,
            'done' => 0,
            'normalized' => 0,
            'error' => 0,
            'skipped' => 0,
            'other' => 0,
        ];

        foreach ($entries as $entry) {
            $summary['total']++;
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if ($status === '') {
                $status = 'pending';
            }

            if (isset($summary[$status])) {
                $summary[$status]++;
                continue;
            }

            $summary['other']++;
        }

        return $summary;
    }

    /**
     * @return array{min_text_chars:int,warn_text_chars:int,min_assets:int}
     */
    private function defaultPayloadPolicy(): array
    {
        return [
            'min_text_chars' => 600,
            'warn_text_chars' => 350,
            'min_assets' => 2,
        ];
    }

    /**
     * @return array{paused:bool,running:bool,persist:bool,remaining:?int}
     */
    private function defaultQueueState(): array
    {
        return [
            'paused' => false,
            'running' => false,
            'persist' => true,
            'remaining' => null,
        ];
    }

    /**
     * @param array<int, array<string, string>> $entries
     */
    private function hasQueueableEntries(array $entries): bool
    {
        foreach ($entries as $entry) {
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if (in_array($status, ['pending', 'error'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, string>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function loadLastResults(UrlHarvestResultStore $resultStore, string $source, array $entries): array
    {
        $results = [];
        foreach (array_slice($entries, 0, 200) as $entry) {
            $url = $entry['url'] ?? '';
            if (!is_string($url) || $url === '') {
                continue;
            }
            if (!$resultStore->hasResult($source, $url)) {
                continue;
            }
            $result = $resultStore->load($source, $url);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStaleEntries(
        string $source,
        array $entries,
        array $queueState,
        UrlHarvestListService $listService,
        bool $workerActive
    ): array {
        // If queue says "running" but worker heartbeat is inactive, recover stale "processing" rows.
        if (!empty($queueState['running']) && $workerActive) {
            return $entries;
        }

        if (!empty($queueState['running']) && !$workerActive) {
            $queueState['running'] = false;
            $listService->writeQueueState($source, $queueState);
        }

        $changed = false;
        foreach ($entries as &$entry) {
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if (in_array($status, ['processing', 'queued'], true)) {
                $entry['status'] = 'pending';
                $changed = true;
            }
        }
        unset($entry);

        if ($changed) {
            $listService->saveEntries($source, $entries);
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function orderEntriesForDisplay(array $entries): array
    {
        $priority = [
            'processing',
            'queued',
            'pending',
            'error',
            'normalized',
            'skipped',
            'done',
        ];

        $buckets = [];
        foreach ($entries as $index => $entry) {
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if ($status === '') {
                $status = 'pending';
            }
            $entry['_display_index'] = $index;
            $buckets[$status][] = $entry;
        }

        foreach ($buckets as &$bucket) {
            usort($bucket, static function (array $a, array $b): int {
                $aTs = strtotime((string) ($a['last_run_at'] ?? '')) ?: 0;
                $bTs = strtotime((string) ($b['last_run_at'] ?? '')) ?: 0;
                if ($aTs !== $bTs) {
                    return $bTs <=> $aTs;
                }

                return ((int) ($a['_display_index'] ?? 0)) <=> ((int) ($b['_display_index'] ?? 0));
            });
        }
        unset($bucket);

        foreach ($buckets as &$bucket) {
            foreach ($bucket as &$entry) {
                unset($entry['_display_index']);
            }
            unset($entry);
        }
        unset($bucket);

        $ordered = [];
        foreach ($priority as $status) {
            if (!empty($buckets[$status])) {
                $ordered = array_merge($ordered, $buckets[$status]);
                unset($buckets[$status]);
            }
        }

        foreach ($buckets as $rest) {
            $ordered = array_merge($ordered, $rest);
        }

        return $ordered;
    }
}
