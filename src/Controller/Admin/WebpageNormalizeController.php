<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use App\Service\ScraperUserResolver;
use App\Security\Voter\ScraperAccessVoter;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


/**
 * Admin controller to normalize project data from raw HTML content using AI.
 */
#[Route('/admin/project/normalize-html', name: 'admin_project_normalize_html', methods: ['GET', 'POST'])]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class WebpageNormalizeController extends AbstractController
{
    public function __invoke(
        Request $request,
        WebpageContentExtractor $extractor,
        NormalizedProjectPersister $persister,
        ScraperUserResolver $scraperUserResolver,
        string $appNormalizeHtmlPromptPath,
        string $appScraperModel
    ): Response {
        $rawHtml = trim((string) $request->request->get('raw_html', ''));
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        $result = null;
        $error = null;
        $created = null;
        $persist = (bool) $request->request->get('persist', false);
        $creator = null;

        $extracted = ['text' => '', 'links' => [], 'images' => []];

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

        if ($rawHtml !== '' && $request->isMethod('POST')) {
            $extracted = $extractor->extract($rawHtml);

            $userContent = "### TEXT\n" . $extracted['text'] .
                "\n\n### LINKS\n" . implode("\n", $extracted['links']) .
                "\n\n### IMAGES\n" . implode("\n", $extracted['images']);

            try {
                $prompt = file_get_contents($appNormalizeHtmlPromptPath);
                if ($prompt === false) {
                    throw new \RuntimeException('Prompt introuvable.');
                }
                if ($promptExtra !== '') {
                    $prompt = rtrim($prompt) . "\n\n" . $promptExtra;
                }

                $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
                $response = $client->chat()->create([
                    'model' => $appScraperModel,
                    'temperature' => 0.3,
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                ]);

                $result = $response->choices[0]->message->content ?? '';

                if ($persist && $result && $creator) {
                    $data = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                    $created = $persister->persist($data, $creator);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/project_normalize_html.html.twig', [
            'rawHtml' => $rawHtml,
            'promptExtra' => $promptExtra,
            'extracted' => $extracted,
            'result' => $result,
            'error' => $error,
            'created' => $created,
            'persist' => $persist,
        ]);
    }
}
