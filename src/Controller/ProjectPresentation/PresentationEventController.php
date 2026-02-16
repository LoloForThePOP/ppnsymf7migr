<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Entity\PresentationEvent;
use App\Service\PresentationEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PresentationEventController extends AbstractController
{
    private const CLIENT_ALLOWED_TYPES = [
        PresentationEvent::TYPE_SHARE_OPEN,
        PresentationEvent::TYPE_SHARE_COPY,
        PresentationEvent::TYPE_SHARE_EXTERNAL,
        PresentationEvent::TYPE_HOME_FEED_IMPRESSION,
        PresentationEvent::TYPE_HOME_FEED_CLICK,
    ];

    private const ALLOWED_SHARE_CHANNELS = [
        'mastodon',
        'bluesky',
        'linkedin',
        'facebook',
        'whatsapp',
        'snapchat',
        'x',
        'email',
    ];
    private const ALLOWED_FEED_PLACEMENTS = [
        'homepage',
    ];
    private const MAX_PAYLOAD_BYTES = 2048;

    #[Route('/pp/{stringId}/event', name: 'pp_event', methods: ['POST'])]
    #[IsGranted('view', subject: 'presentation')]
    public function logEvent(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        PresentationEventLogger $eventLogger,
        #[Autowire(service: 'limiter.presentation_event')] RateLimiterFactory $eventLimiter,
    ): JsonResponse {
        $payload = $this->extractPayload($request);
        if ($payload === null) {
            return $this->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        $type = $payload['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return $this->json(['ok' => false, 'error' => 'missing_type'], 400);
        }
        if (!in_array($type, self::CLIENT_ALLOWED_TYPES, true)) {
            return $this->json(['ok' => false, 'error' => 'invalid_type'], 400);
        }

        $meta = $payload['meta'] ?? [];
        if (!is_array($meta)) {
            return $this->json(['ok' => false, 'error' => 'invalid_meta'], 400);
        }

        $safeMeta = $this->sanitizeMeta($type, $meta);
        if ($safeMeta === null) {
            return $this->json(['ok' => false, 'error' => 'invalid_meta'], 400);
        }

        $limiterKey = $this->buildLimiterKey($request, $presentation, $type, $safeMeta);
        $limit = $eventLimiter->create($limiterKey)->consume(1);
        if (!$limit->isAccepted()) {
            return $this->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $event = $eventLogger->log($presentation, $type, $safeMeta, true);
        if (!$event instanceof PresentationEvent) {
            return $this->json(['ok' => false, 'error' => 'invalid_type'], 400);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function extractPayload(Request $request): ?array
    {
        $content = $request->getContent();
        if (is_string($content) && $content !== '') {
            if (strlen($content) > self::MAX_PAYLOAD_BYTES) {
                return null;
            }

            try {
                $decoded = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            return is_array($decoded) ? $decoded : null;
        }

        $payload = $request->request->all();
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string,string|int>|null
     */
    private function sanitizeMeta(string $type, array $meta): ?array
    {
        if ($type === PresentationEvent::TYPE_SHARE_EXTERNAL) {
            return $this->sanitizeShareExternalMeta($meta);
        }

        if ($type === PresentationEvent::TYPE_HOME_FEED_IMPRESSION || $type === PresentationEvent::TYPE_HOME_FEED_CLICK) {
            return $this->sanitizeHomeFeedMeta($meta);
        }

        return [];
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string,string>|null
     */
    private function sanitizeShareExternalMeta(array $meta): ?array
    {
        $channel = $meta['channel'] ?? null;
        if (!is_string($channel)) {
            return null;
        }

        $channel = strtolower(trim($channel));
        if ($channel === '' || !in_array($channel, self::ALLOWED_SHARE_CHANNELS, true)) {
            return null;
        }

        return ['channel' => $channel];
    }

    /**
     * @param array<string,string|int> $safeMeta
     */
    private function buildLimiterKey(Request $request, PPBase $presentation, string $type, array $safeMeta): string
    {
        $actor = 'anon';
        $user = $this->getUser();
        if ($user && method_exists($user, 'getUserIdentifier')) {
            $actor = 'u:' . (string) $user->getUserIdentifier();
        } elseif ($request->getClientIp()) {
            $actor = 'ip:' . (string) $request->getClientIp();
        }

        if ($type === PresentationEvent::TYPE_HOME_FEED_IMPRESSION || $type === PresentationEvent::TYPE_HOME_FEED_CLICK) {
            $placement = isset($safeMeta['placement']) && is_string($safeMeta['placement'])
                ? $safeMeta['placement']
                : 'homepage';

            return $actor . '|home-feed:' . $placement;
        }

        $presentationId = $presentation->getStringId() ?: (string) $presentation->getId();

        return $actor . '|pp:' . $presentationId;
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string,string|int>|null
     */
    private function sanitizeHomeFeedMeta(array $meta): ?array
    {
        $block = $meta['block'] ?? null;
        if (!is_string($block)) {
            return null;
        }

        $block = trim(strtolower($block));
        if ($block === '' || !preg_match('/^[a-z0-9_-]{2,80}$/', $block)) {
            return null;
        }

        $placement = strtolower(trim((string) ($meta['placement'] ?? 'homepage')));
        if (!in_array($placement, self::ALLOWED_FEED_PLACEMENTS, true)) {
            return null;
        }

        $clean = [
            'placement' => $placement,
            'block' => $block,
        ];

        $blockPosition = $meta['block_position'] ?? null;
        if (is_numeric($blockPosition)) {
            $value = (int) $blockPosition;
            if ($value >= 1 && $value <= 20) {
                $clean['block_position'] = $value;
            }
        }

        $cardPosition = $meta['card_position'] ?? null;
        if (is_numeric($cardPosition)) {
            $value = (int) $cardPosition;
            if ($value >= 1 && $value <= 50) {
                $clean['card_position'] = $value;
            }
        }

        return $clean;
    }
}
