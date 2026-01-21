<?php

namespace App\Service;

use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\PPBaseRepository;
use OpenAI;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UrlHarvestRunner
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly WebpageContentExtractor $extractor,
        private readonly NormalizedProjectPersister $persister,
        private readonly UrlSafetyChecker $urlSafetyChecker,
        private readonly ImageDownloader $imageDownloader,
        private readonly PPBaseRepository $ppBaseRepository,
    ) {
    }

    /**
     * @param array{min_text_chars:int,warn_text_chars:int,min_assets:int} $payloadPolicy
     * @return array<string, mixed>
     */
    public function run(
        string $url,
        bool $persist,
        string $prompt,
        string $model,
        ?User $creator,
        array $payloadPolicy,
        bool $enforcePayloadGate
    ): array {
        $entry = ['url' => $url];

        try {
            $fetch = $this->fetchHtmlWithSafeRedirects($url);
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

            $extracted = $this->extractor->extract($html, $finalUrl);
            $entry['debug']['links'] = $extracted['links'] ?? [];
            $entry['debug']['images'] = $extracted['images'] ?? [];
            $entry['payload'] = $this->assessPayload($extracted, $payloadPolicy);
            if ($enforcePayloadGate && $entry['payload']['status'] === 'too_thin') {
                $entry['skip_persist'] = true;
                $entry['skip_reason'] = 'Payload trop faible';
                $persist = false;
            }

            $userContent = "URL: {$url}\n\n### TEXT\n{$extracted['text']}\n\n### LINKS\n"
                . implode("\n", $extracted['links'])
                . "\n\n### IMAGES\n"
                . implode("\n", $extracted['images']);

            $content = $this->askModel($prompt, $userContent, $model);
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
                $entry['ai_payload'] = $this->normalizeAiPayload($data['payload_assessment'] ?? null);
                if ($enforcePayloadGate && !$this->hasSkipPersist($entry)) {
                    $aiStatus = $entry['ai_payload']['status'] ?? '';
                    if ($aiStatus === 'too_thin') {
                        $entry['skip_persist'] = true;
                        $reason = trim((string) ($entry['ai_payload']['reason'] ?? ''));
                        $entry['skip_reason'] = $reason !== '' ? $reason : 'Payload jugé trop faible par l’IA';
                        $persist = false;
                    }
                }

                $sourceUrl = is_string($data['source_url'] ?? null) ? trim($data['source_url']) : '';
                if ($sourceUrl === '') {
                    $sourceUrl = $finalUrl ?: $url;
                }
                $logoUrl = is_string($data['logo_url'] ?? null) ? trim((string) $data['logo_url']) : '';
                if ($logoUrl !== '') {
                    $entry['debug']['logo_probe'] = $this->imageDownloader->debugDownload($logoUrl, $sourceUrl ?: null);
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
                    $existing = $this->ppBaseRepository->createQueryBuilder('p')
                        ->where('p.ingestion.sourceUrl = :url')
                        ->setParameter('url', $sourceUrl)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                    if ($existing instanceof PPBase) {
                        $entry['duplicate'] = true;
                        $entry['created'] = $existing;
                        return $entry;
                    }
                }

                $created = $this->persister->persist($data, $creator);
                $entry['places_debug'] = $this->persister->getLastPlaceDebug();
                $entry['debug']['logo_saved'] = $created->getLogo() ?: null;
                $entry['debug']['media'] = $this->persister->getLastMediaDebug();
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
     * @return array{status:string,reason:string}|null
     */
    private function normalizeAiPayload(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, ['ok', 'weak', 'too_thin'], true)) {
            return null;
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        return [
            'status' => $status,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function hasSkipPersist(array $entry): bool
    {
        return !empty($entry['skip_persist']);
    }

    private function askModel(string $prompt, string $userContent, string $model): string
    {
        $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
        $resp = $client->chat()->create([
            'model' => $model,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        return $resp->choices[0]->message->content ?? '';
    }

    /**
     * @return array{html: ?string, error: ?string, final_url: ?string}
     */
    private function fetchHtmlWithSafeRedirects(string $url, int $maxRedirects = 3): array
    {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!$this->urlSafetyChecker->isAllowed($currentUrl)) {
                return ['html' => null, 'error' => 'URL non autorisée.', 'final_url' => $currentUrl];
            }

            try {
                $response = $this->httpClient->request('GET', $currentUrl, [
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
