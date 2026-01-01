<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use App\Service\UrlSafetyChecker;
use App\Service\ScraperUserResolver;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;

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
        string $appNormalizeHtmlPromptPath,
        string $appScraperModel
    ): Response {
        $urlsText = trim((string) $request->request->get('urls', ''));
        $persist = (bool) $request->request->get('persist', false);
        $results = [];
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

        if ($urlsText !== '' && $request->isMethod('POST')) {
            $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $urlsText))));
            $urls = array_slice($urls, 0, 10); // guardrail

            $prompt = file_get_contents($appNormalizeHtmlPromptPath);
            if ($prompt === false) {
                $results[] = ['url' => null, 'error' => 'Prompt introuvable.'];
            } else {
                $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');

                foreach ($urls as $url) {
                    $entry = ['url' => $url];
                    try {
                        $fetch = $this->fetchHtmlWithSafeRedirects($httpClient, $urlSafetyChecker, $url);
                        if ($fetch['error'] !== null) {
                            throw new \RuntimeException($fetch['error']);
                        }
                        $html = $fetch['html'] ?? '';
                        $extracted = $extractor->extract($html);

                        $userContent = "URL: {$url}\n\n### TEXT\n{$extracted['text']}\n\n### LINKS\n" . implode("\n", $extracted['links']) . "\n\n### IMAGES\n" . implode("\n", $extracted['images']);

                        $resp = $client->chat()->create([
                            'model' => $appScraperModel,
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
                            $created = $persister->persist($data, $creator);
                        }

                        if ($created) {
                            $entry['created'] = $created;
                        }
                    } catch (\Throwable $e) {
                        $entry['error'] = $e->getMessage();
                    }

                    $results[] = $entry;
                }
            }
        }

        return $this->render('admin/project_harvest_urls.html.twig', [
            'urls' => $urlsText,
            'persist' => $persist,
            'results' => $results,
        ]);
    }

    /**
     * @return array{html: ?string, error: ?string}
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
                return ['html' => null, 'error' => 'URL non autorisée.'];
            }

            try {
                $response = $httpClient->request('GET', $currentUrl, [
                    'timeout' => 10,
                    'max_redirects' => 0,
                ]);
            } catch (TransportExceptionInterface) {
                return ['html' => null, 'error' => 'Erreur réseau.'];
            }

            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;
                if (!is_string($location) || $location === '') {
                    return ['html' => null, 'error' => 'Redirection invalide.'];
                }

                $resolved = $this->resolveRedirectUrl($location, $currentUrl);
                if ($resolved === null) {
                    return ['html' => null, 'error' => 'URL de redirection invalide.'];
                }

                $currentUrl = $resolved;
                continue;
            }

            if ($status !== 200) {
                return ['html' => null, 'error' => sprintf('Status HTTP %d', $status)];
            }

            return ['html' => $response->getContent(false), 'error' => null];
        }

        return ['html' => null, 'error' => 'Trop de redirections.'];
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
}
