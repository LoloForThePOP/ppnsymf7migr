<?php

namespace App\Controller\Admin;

use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NormalizedProjectPersister;
use App\Service\ScraperUserResolver;
use App\Security\Voter\ScraperAccessVoter;

class ProjectNormalizeController extends AbstractController
{
    /**
     * Admin controller to normalize project data from raw text using AI.
     */
    #[Route('/admin/project/normalize', name: 'admin_project_normalize', methods: ['GET', 'POST'])]
    #[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
    public function __invoke(
        Request $request,
        string $appNormalizeTextPromptPath,
        string $appScraperModel,
        NormalizedProjectPersister $persister,
        ScraperUserResolver $scraperUserResolver
    ): Response {
        $raw = trim((string) $request->request->get('raw_text', ''));
        $persist = (bool) $request->request->get('persist', false);
        $result = null;
        $error = null;
        $created = null;
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

        if ($raw !== '' && $request->isMethod('POST')) {
            try {
                $prompt = file_get_contents($appNormalizeTextPromptPath);
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

                if ($persist && $result && $creator) {
                    $data = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                    $created = $persister->persist($data, $creator);
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
