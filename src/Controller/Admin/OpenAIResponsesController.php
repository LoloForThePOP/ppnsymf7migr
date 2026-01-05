<?php

namespace App\Controller\Admin;

use App\Security\Voter\ScraperAccessVoter;
use OpenAI\Factory;
use OpenAI\Responses\Responses\Output\OutputReasoning;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
final class OpenAIResponsesController extends AbstractController
{
    #[Route('/admin/openai/responses', name: 'admin_openai_responses', methods: ['GET'])]
    public function index(string $appScraperModel, string $appNormalizePromptPath): Response
    {
        $defaultInstructions = '';
        $prompt = file_get_contents($appNormalizePromptPath);
        if ($prompt !== false) {
            $defaultInstructions = trim($prompt);
        }

        return $this->render('admin/openai_responses.html.twig', [
            'defaultModel' => $appScraperModel,
            'defaultInstructions' => $defaultInstructions,
        ]);
    }

    #[Route('/admin/openai/responses/run', name: 'admin_openai_responses_run', methods: ['POST'])]
    public function run(Request $request, string $appScraperModel): JsonResponse
    {
        $input = trim((string) $request->request->get('input', ''));
        if ($input === '') {
            return $this->json(['error' => 'Input is empty.'], 400);
        }

        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $this->json(['error' => 'OPENAI_API_KEY is missing.'], 400);
        }

        $model = trim((string) $request->request->get('model', $appScraperModel));
        if ($model === '') {
            $model = $appScraperModel;
        }

        $instructions = trim((string) $request->request->get('instructions', ''));
        $useWebSearch = $request->request->getBoolean('web_search');
        $reasoningEffort = trim((string) $request->request->get('reasoning_effort', ''));
        $includeRaw = $request->request->getBoolean('include_raw');

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if ($instructions !== '') {
            $payload['instructions'] = $instructions;
        }

        if ($useWebSearch) {
            $payload['tools'] = [
                ['type' => 'web_search'],
            ];
        }

        if (in_array($reasoningEffort, ['low', 'medium', 'high'], true)) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        try {
            $factory = (new Factory())->withApiKey($apiKey);
            $baseUri = trim((string) ($_ENV['OPENAI_BASE_URI'] ?? ''));
            if ($baseUri !== '') {
                $factory = $factory->withBaseUri($baseUri);
            }
            $client = $factory->make();
            $response = $client->responses()->create($payload);

            $reasoningSummary = [];
            foreach ($response->output as $outputItem) {
                if (!$outputItem instanceof OutputReasoning) {
                    continue;
                }
                foreach ($outputItem->summary as $summary) {
                    $text = trim($summary->text);
                    if ($text !== '') {
                        $reasoningSummary[] = $text;
                    }
                }
            }

            return $this->json([
                'output_text' => $response->outputText ?? '',
                'reasoning_summary' => $reasoningSummary,
                'usage' => $response->usage?->toArray(),
                'raw' => $includeRaw ? $response->toArray() : null,
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], 500);
        }
    }
}
