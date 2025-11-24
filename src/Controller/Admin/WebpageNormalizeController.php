<?php

namespace App\Controller\Admin;

use App\Service\WebpageContentExtractor;
use App\Service\NormalizedProjectPersister;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project/normalize-html', name: 'admin_project_normalize_html', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_ADMIN')]
class WebpageNormalizeController extends AbstractController
{
    public function __invoke(
        Request $request,
        WebpageContentExtractor $extractor,
        NormalizedProjectPersister $persister,
        string $appNormalizeHtmlPromptPath,
        string $appScraperModel,
        int $defaultCreatorId
    ): Response {
        $rawHtml = trim((string) $request->request->get('raw_html', ''));
        $result = null;
        $error = null;
        $created = null;
        $persist = (bool) $request->request->get('persist', false);

        $extracted = ['text' => '', 'links' => [], 'images' => []];

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

                if ($persist && $result) {
                    $data = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                    $created = $persister->persist($data, $defaultCreatorId);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/project_normalize_html.html.twig', [
            'rawHtml' => $rawHtml,
            'extracted' => $extracted,
            'result' => $result,
            'error' => $error,
            'created' => $created,
        ]);
    }
}
