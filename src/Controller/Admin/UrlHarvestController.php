<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use App\Service\UrlSafetyChecker;
use App\Service\ScraperUserResolver;
use App\Service\UrlHarvestListService;
use App\Service\ImageDownloader;
use App\Repository\PPBaseRepository;
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
        ImageDownloader $imageDownloader,
        PPBaseRepository $ppBaseRepository,
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
        $sourcePayloadPolicy = $selectedSource !== '' ? $listService->readPayloadPolicy($selectedSource) : $this->defaultPayloadPolicy();
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
                            $imageDownloader,
                            $ppBaseRepository,
                            $creator,
                            $persistSource,
                            $prompt,
                            $appScraperModel,
                            $sourcePayloadPolicy,
                            true
                        );

                        $sourceResults[] = $result;
                        $sourceEntries[$index]['last_run_at'] = $now->format('Y-m-d H:i:s');
                        if (isset($result['payload']) && is_array($result['payload'])) {
                            $sourceEntries[$index]['payload_status'] = (string) ($result['payload']['status'] ?? '');
                            $sourceEntries[$index]['payload_text_chars'] = (string) ($result['payload']['text_chars'] ?? '');
                            $sourceEntries[$index]['payload_links'] = (string) ($result['payload']['links'] ?? '');
                            $sourceEntries[$index]['payload_images'] = (string) ($result['payload']['images'] ?? '');
                            $payloadNote = $this->formatPayloadNote($result['payload']);
                            $sourceEntries[$index]['notes'] = $this->mergeNotes(
                                (string) ($sourceEntries[$index]['notes'] ?? ''),
                                $payloadNote
                            );
                        }

                        if (!empty($result['error'])) {
                            $sourceEntries[$index]['status'] = 'error';
                            $sourceEntries[$index]['error'] = $result['error'];
                        } elseif (!empty($result['skip_persist'])) {
                            $sourceEntries[$index]['status'] = 'skipped';
                            $sourceEntries[$index]['error'] = $result['skip_reason'] ?? 'Payload trop faible';
                            $sourceEntries[$index]['created_string_id'] = '';
                            $sourceEntries[$index]['created_url'] = '';
                        } else {
                            if (!empty($result['duplicate'])) {
                                $sourceEntries[$index]['status'] = 'skipped';
                                $sourceEntries[$index]['error'] = 'Doublon';
                                if (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
                                    $sourceEntries[$index]['created_string_id'] = $result['created']->getStringId();
                                    $sourceEntries[$index]['created_url'] = $this->generateUrl(
                                        'edit_show_project_presentation',
                                        ['stringId' => $result['created']->getStringId()],
                                        UrlGeneratorInterface::ABSOLUTE_PATH
                                    );
                                } else {
                                    $sourceEntries[$index]['created_string_id'] = '';
                                    $sourceEntries[$index]['created_url'] = '';
                                }
                            } elseif (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
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
                        $imageDownloader,
                        $ppBaseRepository,
                        $creator,
                        $persist,
                        $prompt,
                        $appScraperModel,
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
        ImageDownloader $imageDownloader,
        PPBaseRepository $ppBaseRepository,
        mixed $creator,
        bool $persist,
        string $prompt,
        string $model,
        array $payloadPolicy,
        bool $enforcePayloadGate
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
            $entry['payload'] = $this->assessPayload($extracted, $payloadPolicy);
            if ($enforcePayloadGate && $entry['payload']['status'] === 'too_thin') {
                $entry['skip_persist'] = true;
                $entry['skip_reason'] = 'Payload trop faible';
                $persist = false;
            }

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
            $data = null;

            if ($content) {
                try {
                    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    if ($persist) {
                        throw $e;
                    }
                    $entry['debug']['json_error'] = $e->getMessage();
                }
            }

            if (is_array($data)) {
                $sourceUrl = is_string($data['source_url'] ?? null) ? trim($data['source_url']) : '';
                if ($sourceUrl === '') {
                    $sourceUrl = $finalUrl ?: $url;
                }
                $logoUrl = is_string($data['logo_url'] ?? null) ? trim((string) $data['logo_url']) : '';
                if ($logoUrl !== '') {
                    $entry['debug']['logo_probe'] = $imageDownloader->debugDownload($logoUrl, $sourceUrl ?: null);
                }
            }

            if ($persist && $content && $creator) {
                if (!is_array($data)) {
                    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                }
                if ($fundingEndAt !== null && empty($data['funding_end_at'])) {
                    $data['funding_end_at'] = $fundingEndAt->format('Y-m-d');
                }
                $sourceUrl = is_string($data['source_url'] ?? null) ? trim($data['source_url']) : '';
                if ($sourceUrl === '') {
                    $sourceUrl = $finalUrl ?: $url;
                }
                if ($sourceUrl !== '') {
                    $existing = $ppBaseRepository->createQueryBuilder('p')
                        ->where('p.ingestion.sourceUrl = :url')
                        ->setParameter('url', $sourceUrl)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                    if ($existing instanceof \App\Entity\PPBase) {
                        $entry['duplicate'] = true;
                        $entry['created'] = $existing;
                        return $entry;
                    }
                }
                $created = $persister->persist($data, $creator);
                $entry['places_debug'] = $persister->getLastPlaceDebug();
                $entry['debug']['logo_saved'] = $created->getLogo() ?: null;
                $entry['debug']['media'] = $persister->getLastMediaDebug();
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
     * @param array{links?:array<int,string>,images?:array<int,string>,text?:string} $extracted
     * @param array{min_text_chars:int,warn_text_chars:int,min_assets:int} $policy
     * @return array{status:string,text_chars:int,links:int,images:int,assets:int}
     */
    private function assessPayload(array $extracted, array $policy): array
    {
        $text = trim((string) ($extracted['text'] ?? ''));
        $textChars = $this->countChars($text);
        $links = is_array($extracted['links'] ?? null) ? count($extracted['links']) : 0;
        $images = is_array($extracted['images'] ?? null) ? count($extracted['images']) : 0;
        $assets = $links + $images;

        $minText = max(0, (int) ($policy['min_text_chars'] ?? 0));
        $warnText = max(0, (int) ($policy['warn_text_chars'] ?? 0));
        $minAssets = max(0, (int) ($policy['min_assets'] ?? 0));

        if ($textChars >= $minText) {
            $status = 'ok';
        } elseif ($textChars >= $warnText || $assets >= $minAssets) {
            $status = 'weak';
        } else {
            $status = 'too_thin';
        }

        return [
            'status' => $status,
            'text_chars' => $textChars,
            'links' => $links,
            'images' => $images,
            'assets' => $assets,
        ];
    }

    /**
     * @param array{status?:string,text_chars?:int,links?:int,images?:int} $payload
     */
    private function formatPayloadNote(array $payload): string
    {
        $status = (string) ($payload['status'] ?? '');
        if ($status === '' || $status === 'ok') {
            return '';
        }

        $textChars = (int) ($payload['text_chars'] ?? 0);
        $links = (int) ($payload['links'] ?? 0);
        $images = (int) ($payload['images'] ?? 0);

        return sprintf(
            'Payload %s (%d car., %d liens, %d images)',
            $status === 'weak' ? 'faible' : 'trop faible',
            $textChars,
            $links,
            $images
        );
    }

    private function mergeNotes(string $current, string $payloadNote): string
    {
        $current = trim($current);
        $payloadNote = trim($payloadNote);
        if ($payloadNote === '') {
            return $current;
        }
        if ($current === '') {
            return $payloadNote;
        }
        if (str_contains($current, $payloadNote)) {
            return $current;
        }

        return $current . ' | ' . $payloadNote;
    }

    private function countChars(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
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
