<?php

namespace App\Controller\Admin;

use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NormalizedProjectPersister;

class ProjectNormalizeController extends AbstractController
{
    #[Route('/admin/project/normalize', name: 'admin_project_normalize', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(
        Request $request,
        string $appNormalizePromptPath,
        string $appScraperModel,
        NormalizedProjectPersister $persister,
        int $defaultCreatorId
    ): Response {
        $raw = trim((string) $request->request->get('raw_text', ''));
        $persist = (bool) $request->request->get('persist', false);
        $result = null;
        $error = null;
        $created = null;

        if ($raw !== '' && $request->isMethod('POST')) {
            try {
                $prompt = file_get_contents($appNormalizePromptPath);
                if ($prompt === false) {
                    throw new \RuntimeException('Prompt introuvable.');
                }

                $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
                $response = $client->chat()->create([
                    'model' => $appScraperModel,
                    'temperature' => 0.3,
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => $raw],
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

        return $this->render('admin/project_normalize.html.twig', [
            'raw' => $raw,
            'result' => $result,
            'error' => $error,
            'created' => $created,
        ]);
    }
}
