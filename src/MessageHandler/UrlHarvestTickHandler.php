<?php

namespace App\MessageHandler;

use App\Message\UrlHarvestTickMessage;
use App\Service\UrlHarvestListService;
use App\Service\UrlHarvestResultStore;
use App\Service\UrlHarvestRunner;
use App\Service\WorkerHeartbeatService;
use App\Service\ScraperUserResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class UrlHarvestTickHandler
{
    public function __construct(
        private readonly UrlHarvestListService $listService,
        private readonly UrlHarvestRunner $runner,
        private readonly UrlHarvestResultStore $resultStore,
        private readonly ScraperUserResolver $scraperUserResolver,
        private readonly WorkerHeartbeatService $workerHeartbeat,
        private readonly MessageBusInterface $bus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appNormalizeHtmlPromptPath,
        private readonly string $appScraperModel,
    ) {
    }

    public function __invoke(UrlHarvestTickMessage $message): void
    {
        $this->workerHeartbeat->touch('url_harvest');
        $source = $message->getSource();
        $queueState = $this->listService->readQueueState($source);
        if ($queueState['paused']) {
            $queueState['running'] = false;
            $this->listService->writeQueueState($source, $queueState);
            return;
        }

        $lock = $this->acquireLock($source);
        if ($lock === null) {
            return;
        }

        try {
            $loaded = $this->listService->loadEntries($source);
            $entries = $loaded['entries'];
            if ($loaded['error'] !== null) {
                $queueState['running'] = false;
                $this->listService->writeQueueState($source, $queueState);
                return;
            }

            $nextIndex = $this->findNextQueueableIndex($entries);
            if ($nextIndex === null) {
                $queueState['running'] = false;
                $this->listService->writeQueueState($source, $queueState);
                return;
            }

            $entries[$nextIndex]['status'] = 'processing';
            $entries[$nextIndex]['last_run_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $entries[$nextIndex]['error'] = '';
            $this->listService->saveEntries($source, $entries);

            $prompt = $this->buildPrompt($source);
            if ($prompt === null) {
                $entries[$nextIndex]['status'] = 'error';
                $entries[$nextIndex]['error'] = 'Prompt introuvable.';
                $this->listService->saveEntries($source, $entries);
                $queueState['running'] = false;
                $this->listService->writeQueueState($source, $queueState);
                return;
            }

            $creator = $queueState['persist'] ? $this->scraperUserResolver->resolve() : null;
            $payloadPolicy = $this->listService->readPayloadPolicy($source);

            try {
                $result = $this->runner->run(
                    $entries[$nextIndex]['url'],
                    $queueState['persist'] && $creator !== null,
                    $prompt,
                    $this->appScraperModel,
                    $creator,
                    $payloadPolicy,
                    true
                );
            } catch (\Throwable $e) {
                $result = [
                    'url' => $entries[$nextIndex]['url'],
                    'error' => $e->getMessage(),
                    'payload' => [],
                    'ai_payload' => [],
                ];
            }

            $entries = $this->applyResult($entries, $nextIndex, $result);
            $this->listService->saveEntries($source, $entries);

            $storedResult = $result;
            if (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
                $storedResult['created_string_id'] = $result['created']->getStringId();
                $storedResult['created_title'] = $result['created']->getTitle() ?? $result['created']->getGoal();
                unset($storedResult['created']);
            }
            $this->resultStore->store($source, $entries[$nextIndex]['url'], $storedResult);

            $queueState = $this->listService->readQueueState($source);
            if ($queueState['paused']) {
                $queueState['running'] = false;
                $this->listService->writeQueueState($source, $queueState);
                return;
            }

            if (is_int($queueState['remaining'])) {
                $queueState['remaining'] = max(0, $queueState['remaining'] - 1);
            }

            $queueState['running'] = $this->hasQueueable($entries)
                && (!is_int($queueState['remaining']) || $queueState['remaining'] > 0);
            $this->listService->writeQueueState($source, $queueState);

            if ($queueState['running']) {
                $this->bus->dispatch(new UrlHarvestTickMessage($source));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function buildPrompt(string $source): ?string
    {
        $prompt = file_get_contents($this->appNormalizeHtmlPromptPath);
        if ($prompt === false) {
            return null;
        }

        $sourcePrompt = $this->listService->readPrompt($source);
        if ($sourcePrompt !== '') {
            $prompt = rtrim($prompt) . "\n\n" . $sourcePrompt;
        }

        return $prompt;
    }

    /**
     * @param array<int, array<string, string>> $entries
     */
    private function findNextQueueableIndex(array $entries): ?int
    {
        foreach ($entries as $index => $entry) {
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if (in_array($status, ['pending', 'error'], true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, string>> $entries
     * @param array<string, mixed> $result
     * @return array<int, array<string, string>>
     */
    private function applyResult(array $entries, int $index, array $result): array
    {
        $now = new \DateTimeImmutable();
        $entries[$index]['last_run_at'] = $now->format('Y-m-d H:i:s');
        $entries[$index]['payload_status'] = (string) ($result['payload']['status'] ?? '');
        $entries[$index]['payload_text_chars'] = (string) ($result['payload']['text_chars'] ?? '');
        $entries[$index]['payload_links'] = (string) ($result['payload']['links'] ?? '');
        $entries[$index]['payload_images'] = (string) ($result['payload']['images'] ?? '');
        $entries[$index]['ai_payload_status'] = (string) ($result['ai_payload']['status'] ?? '');
        $entries[$index]['ai_payload_reason'] = (string) ($result['ai_payload']['reason'] ?? '');
        $entries[$index]['notes'] = $this->mergeNotes(
            (string) ($entries[$index]['notes'] ?? ''),
            $this->formatPayloadNote($result['payload'] ?? [])
        );

        if (!empty($result['error'])) {
            $entries[$index]['status'] = 'error';
            $entries[$index]['error'] = (string) $result['error'];
            $entries[$index]['created_string_id'] = '';
            $entries[$index]['created_url'] = '';
            return $entries;
        }

        if (!empty($result['skip_persist'])) {
            $entries[$index]['status'] = 'skipped';
            $entries[$index]['error'] = (string) ($result['skip_reason'] ?? 'Payload trop faible');
            $entries[$index]['created_string_id'] = '';
            $entries[$index]['created_url'] = '';
            return $entries;
        }

        if (!empty($result['duplicate'])) {
            $entries[$index]['status'] = 'skipped';
            $entries[$index]['error'] = 'Doublon';
            if (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
                $entries[$index]['created_string_id'] = $result['created']->getStringId();
                $entries[$index]['created_url'] = $this->urlGenerator->generate(
                    'edit_show_project_presentation',
                    ['stringId' => $result['created']->getStringId()],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                );
            } else {
                $entries[$index]['created_string_id'] = '';
                $entries[$index]['created_url'] = '';
            }
            return $entries;
        }

        if (!empty($result['created']) && $result['created'] instanceof \App\Entity\PPBase) {
            $entries[$index]['status'] = 'done';
            $entries[$index]['error'] = '';
            $entries[$index]['created_string_id'] = $result['created']->getStringId();
            $entries[$index]['created_url'] = $this->urlGenerator->generate(
                'edit_show_project_presentation',
                ['stringId' => $result['created']->getStringId()],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
            return $entries;
        }

        $entries[$index]['status'] = 'normalized';
        $entries[$index]['error'] = '';
        $entries[$index]['created_string_id'] = '';
        $entries[$index]['created_url'] = '';

        return $entries;
    }

    private function hasQueueable(array $entries): bool
    {
        foreach ($entries as $entry) {
            $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
            if (in_array($status, ['pending', 'error'], true)) {
                return true;
            }
        }

        return false;
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

    private function acquireLock(string $source): mixed
    {
        $path = $this->listService->resolveQueueLockPath($source);
        if ($path === null) {
            return null;
        }

        $handle = fopen($path, 'c');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }
}
