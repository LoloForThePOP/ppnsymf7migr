<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use App\Service\UrlSafetyChecker;
use App\Service\ScraperUserResolver;
use App\Service\UrlHarvestListService;
use OpenAI;
use OpenAI\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/project/harvest-urls', name: 'admin_project_harvest_urls', methods: ['GET', 'POST'])]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
final class UrlHarvestController extends AbstractController
{
    public function __invoke(
        Request $request,
        HttpClientInterface $httpClient,
        WebpageContentExtractor $extractor,
        NormalizedProjectPersister $persister,
        UrlSafetyChecker $urlSafetyChecker,
        ScraperUserResolver $scraperUserResolver,
        UrlHarvestListService $listService,
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
        $sourceEntries = [];
        $sourceError = null;
        $sourceSummary = null;
        $sourceResults = [];
        $batchSize = (int) $request->request->get('batch_size', 10);
        $batchSize = max(1, min(50, $batchSize));
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
                $creator = null;
                if ($persistSource) {
                    $creator = $scraperUserResolver->resolve();
                    if (!$creator) {
                        $this->addFlash('warning', sprintf(
                            'Compte "%s" introuvable ou multiple. Persistance désactivée.',
                            $scraperUserResolver->getRole()
                        ));
                        $persistSource = false;
                    }
                }

                $prompt = file_get_contents($appNormalizeHtmlPromptPath);
                if ($prompt === false) {
                    $sourceError = 'Prompt introuvable.';
                } else {
                    if ($sourcePrompt !== '') {
                        $prompt = rtrim($prompt) . "\n\n" . $sourcePrompt;
                    }
                    $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
                    $processed = 0;
                    $now = new \DateTimeImmutable();

                    foreach ($sourceEntries as $index => $entry) {
                        if ($processed >= $batchSize) {
                            break;
                        }
                        $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
                        if (!$this->isQueueableStatus($status)) {
                            continue;
                        }

                        $result = $this->harvestUrl(
                            $entry['url'],
                            $client,
                            $httpClient,
                            $extractor,
                            $persister,
                            $urlSafetyChecker,
                            $creator,
                            $persistSource,
                            $prompt,
                            $appScraperModel
                        );

                        $sourceResults[] = $result;
                        $sourceEntries[$index]['last_run_at'] = $now->format('Y-m-d H:i:s');

                        if (!empty($result['error'])) {
                            $sourceEntries[$index]['status'] = 'error';
                            $sourceEntries[$index]['error'] = $result['error'];
                        } else {
                            if (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
                                $sourceEntries[$index]['status'] = 'done';
                                $sourceEntries[$index]['error'] = '';
                                $sourceEntries[$index]['created_string_id'] = $result['created']->getStringId();
                                $sourceEntries[$index]['created_url'] = $this->generateUrl(
                                    'edit_show_project_presentation',
                                    ['stringId' => $result['created']->getStringId()],
                                    UrlGeneratorInterface::ABSOLUTE_PATH
                                );
                            } else {
                                $sourceEntries[$index]['status'] = 'normalized';
                                $sourceEntries[$index]['error'] = '';
                                $sourceEntries[$index]['created_string_id'] = '';
                                $sourceEntries[$index]['created_url'] = '';
                            }
                        }

                        $processed++;
                    }

                    try {
                        $listService->saveEntries($selectedSource, $sourceEntries);
                    } catch (\Throwable $e) {
                        $sourceError = $e->getMessage();
                    }

                    $sourceSummary = $this->summarizeEntries($sourceEntries);
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
                $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');

                foreach ($urls as $url) {
                    $results[] = $this->harvestUrl(
                        $url,
                        $client,
                        $httpClient,
                        $extractor,
                        $persister,
                        $urlSafetyChecker,
                        $creator,
                        $persist,
                        $prompt,
                        $appScraperModel
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
        ]);
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

    private function isQueueableStatus(string $status): bool
    {
        if ($status === '') {
            return true;
        }

        return in_array($status, ['pending', 'error'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestUrl(
        string $url,
        Client $client,
        HttpClientInterface $httpClient,
        WebpageContentExtractor $extractor,
        NormalizedProjectPersister $persister,
        UrlSafetyChecker $urlSafetyChecker,
        mixed $creator,
        bool $persist,
        string $prompt,
        string $model
    ): array {
        $entry = ['url' => $url];

        try {
            $fetch = $this->fetchHtmlWithSafeRedirects($httpClient, $urlSafetyChecker, $url);
            if ($fetch['error'] !== null) {
                throw new \RuntimeException($fetch['error']);
            }
            $html = $fetch['html'] ?? '';
            $finalUrl = $fetch['final_url'] ?? $url;
            $daysRemaining = $this->inferFondationPatrimoineDaysRemaining($html, $finalUrl);
            $fundingEndAt = $daysRemaining !== null
                ? (new \DateTimeImmutable())->modify(sprintf('+%d days', $daysRemaining))
                : null;
            $entry['debug'] = [
                'final_url' => $finalUrl,
                'html_preview' => $this->truncateHtml($html),
            ];
            if ($daysRemaining !== null && $fundingEndAt !== null) {
                $entry['debug']['funding_days_remaining'] = $daysRemaining;
                $entry['debug']['funding_end_at_guess'] = $fundingEndAt->format('Y-m-d');
            }
            $extracted = $extractor->extract($html, $finalUrl);
            $entry['debug']['links'] = $extracted['links'] ?? [];
            $entry['debug']['images'] = $extracted['images'] ?? [];

            $userContent = "URL: {$url}\n\n### TEXT\n{$extracted['text']}\n\n### LINKS\n" . implode("\n", $extracted['links']) . "\n\n### IMAGES\n" . implode("\n", $extracted['images']);

            $resp = $client->chat()->create([
                'model' => $model,
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

            $content = $resp->choices[0]->message->content ?? '';
            $entry['raw'] = $content;
            $created = null;

            if ($persist && $content && $creator) {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if ($fundingEndAt !== null && empty($data['funding_end_at'])) {
                    $data['funding_end_at'] = $fundingEndAt->format('Y-m-d');
                }
                $created = $persister->persist($data, $creator);
                $entry['places_debug'] = $persister->getLastPlaceDebug();
            }

            if ($created) {
                $entry['created'] = $created;
            }
        } catch (\Throwable $e) {
            $entry['error'] = $e->getMessage();
        }

        return $entry;
    }

    /**
     * @return array{html: ?string, error: ?string, final_url: ?string}
     */
    private function fetchHtmlWithSafeRedirects(
        HttpClientInterface $httpClient,
        UrlSafetyChecker $urlSafetyChecker,
        string $url,
        int $maxRedirects = 3
    ): array {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!$urlSafetyChecker->isAllowed($currentUrl)) {
                return ['html' => null, 'error' => 'URL non autorisée.', 'final_url' => $currentUrl];
            }

            try {
                $response = $httpClient->request('GET', $currentUrl, [
                    'timeout' => 10,
                    'max_redirects' => 0,
                ]);
            } catch (TransportExceptionInterface) {
                return ['html' => null, 'error' => 'Erreur réseau.', 'final_url' => $currentUrl];
            }

            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;
                if (!is_string($location) || $location === '') {
                    return ['html' => null, 'error' => 'Redirection invalide.', 'final_url' => $currentUrl];
                }

                $resolved = $this->resolveRedirectUrl($location, $currentUrl);
                if ($resolved === null) {
                    return ['html' => null, 'error' => 'URL de redirection invalide.', 'final_url' => $currentUrl];
                }

                $currentUrl = $resolved;
                continue;
            }

            if ($status !== 200) {
                return ['html' => null, 'error' => sprintf('Status HTTP %d', $status), 'final_url' => $currentUrl];
            }

                return [
                    'html' => $response->getContent(false),
                    'error' => null,
                    'final_url' => $currentUrl,
                ];
        }

        return ['html' => null, 'error' => 'Trop de redirections.', 'final_url' => $currentUrl];
    }

    private function truncateHtml(string $html, int $maxLength = 100000): string
    {
        $html = $this->stripLargeBlocks($html);
        if (strlen($html) <= $maxLength) {
            return $html;
        }

        return substr($html, 0, $maxLength) . "\n<!-- HTML truncated -->";
    }

    private function stripLargeBlocks(string $html): string
    {
        $html = preg_replace('#<script\\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\\b[^>]*>.*?</style>#is', '', $html) ?? $html;

        return $html;
    }

    private function resolveRedirectUrl(string $location, string $baseUrl): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['host'])) {
            return null;
        }

        if (str_starts_with($location, '//')) {
            $scheme = $base['scheme'] ?? 'https';
            return $scheme . ':' . $location;
        }

        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $location);
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(dirname($path), '/\\');

        return sprintf('%s://%s%s/%s', $scheme, $host, $port, ltrim($dir . '/' . $location, '/'));
    }

    private function inferFondationPatrimoineDaysRemaining(string $html, ?string $finalUrl): ?int
    {
        if (!$this->isFondationPatrimoineUrl($finalUrl)) {
            return null;
        }

        $patterns = [
            '/data-testid="time-remaining"[^>]*>([^<]+)/i',
            '/\\b(\\d[\\d\\s]{0,6})\\s*jours\\s*restants\\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $html, $matches)) {
                continue;
            }

            $raw = $matches[1] ?? '';
            $digits = preg_replace('/\\D+/', '', $raw);
            if ($digits === '' || $digits === null) {
                continue;
            }

            $days = (int) $digits;
            if ($days > 0 && $days < 5000) {
                return $days;
            }
        }

        return null;
    }

    private function isFondationPatrimoineUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $host = preg_replace('/^(www\\.|m\\.)/i', '', $host);

        return $host === 'fondation-patrimoine.org'
            || str_ends_with($host, '.fondation-patrimoine.org');
    }
}
