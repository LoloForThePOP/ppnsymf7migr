<?php

namespace App\Controller\Admin;

use App\Service\ScraperUserResolver;
use App\Service\UrlHarvestListService;
use App\Service\UrlHarvestRunner;
use App\Service\UrlHarvestResultStore;
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
            $sourceEntries = $loaded['entries'];
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
                $wasRunning = $queueState['running'];
                $queueState['paused'] = false;
                $queueState['running'] = true;
                $queueState['persist'] = $persistSource;
                $queueState['remaining'] = $batchSize > 0 ? $batchSize : null;
                $listService->writeQueueState($selectedSource, $queueState);

                if (!$wasRunning) {
                    $bus->dispatch(new UrlHarvestTickMessage($selectedSource));
                }
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
        ]);
    }

    #[Route('/status', name: 'admin_project_harvest_urls_status', methods: ['GET'])]
    #[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
    public function status(Request $request, UrlHarvestListService $listService, UrlHarvestResultStore $resultStore): JsonResponse
    {
        $source = trim((string) $request->query->get('source', ''));
        if ($source === '') {
            return new JsonResponse(['error' => 'Source manquante.'], Response::HTTP_BAD_REQUEST);
        }

        $loaded = $listService->loadEntries($source);
        if ($loaded['error'] !== null) {
            return new JsonResponse(['error' => $loaded['error']], Response::HTTP_BAD_REQUEST);
        }

        $entries = array_slice($loaded['entries'], 0, 200);
        foreach ($entries as &$entry) {
            $entry['result_key'] = $resultStore->getPublicKey($entry['url']);
            $entry['has_result'] = $resultStore->hasResult($source, $entry['url']);
        }
        unset($entry);

        return new JsonResponse([
            'entries' => $entries,
            'summary' => $this->summarizeEntries($loaded['entries']),
            'queue' => $listService->readQueueState($source),
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
}
