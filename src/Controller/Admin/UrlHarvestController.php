<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project/harvest-urls', name: 'admin_project_harvest_urls', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_ADMIN')]
final class UrlHarvestController extends AbstractController
{
    public function __invoke(
        Request $request,
        HttpClientInterface $httpClient,
        WebpageContentExtractor $extractor,
        NormalizedProjectPersister $persister,
        string $appNormalizeHtmlPromptPath,
        string $appScraperModel,
        int $defaultCreatorId
    ): Response {
        $urlsText = trim((string) $request->request->get('urls', ''));
        $persist = (bool) $request->request->get('persist', false);
        $results = [];

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
                        $response = $httpClient->request('GET', $url, ['timeout' => 10]);
                        if (200 !== $response->getStatusCode()) {
                            throw new \RuntimeException(sprintf('Status HTTP %d', $response->getStatusCode()));
                        }
                        $html = $response->getContent();
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

                        if ($persist && $content) {
                            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                            $created = $persister->persist($data, $defaultCreatorId);
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
}
