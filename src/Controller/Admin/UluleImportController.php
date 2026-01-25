<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use App\Entity\UluleProjectCatalog;
use App\Repository\UluleProjectCatalogRepository;
use App\Service\UluleImportService;
use App\Service\UluleApiClient;
use OpenAI;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class UluleImportController extends AbstractController
{
    private const MAX_SECONDARY_IMAGES = 5;
    private const DEFAULT_PROMPT_EXTRA = 'Ce complément de prompt définit des instructions hautement prioritaires par rapport aux précédentes : Pour chaque image remplit le champ licence avec "Copyright Ulule.fr". N\'inclue pas la localisation (ville/commune/région/pays) dans les keywords, sauf si la localisation fait partie du titre du projet. Pour goal, évite toute sémantique de collecte/soutien/financement (ex: "soutenir", "collecter", "financer") et formule l\'objectif comme la réalisation concrète du projet (ex: "Produire…", "Réaliser…", "Créer…" ou autre tournure).';

    #[Route('/admin/ulule/import/project/{ululeId}', name: 'admin_ulule_import_project', methods: ['POST'])]
    public function importProject(
        int $ululeId,
        Request $request,
        UluleImportService $importService
    ): Response {
        $isAjax = $request->isXmlHttpRequest();
        $redirectUrl = $request->headers->get('referer') ?? $this->generateUrl('admin_ulule_catalog');
        $respond = function (
            string $status,
            string $message,
            array $extra = [],
            int $httpStatus = Response::HTTP_OK
        ) use ($isAjax, $redirectUrl) {
            if ($isAjax) {
                return $this->json(array_merge([
                    'status' => $status,
                    'message' => $message,
                ], $extra), $httpStatus);
            }

            if ($message !== '') {
                $level = match ($status) {
                    'imported' => 'success',
                    'duplicate' => 'info',
                    'skipped' => 'warning',
                    default => 'danger',
                };
                $this->addFlash($level, $message);
            }

            return $this->redirect($redirectUrl);
        };

        $lang = trim((string) $request->request->get('lang', 'fr'));
        $country = trim((string) $request->request->get('country', 'FR'));
        $minDescriptionLength = max(0, (int) $request->request->get('min_description_length', 500));
        $excludeFunded = $request->request->has('exclude_funded')
            ? (bool) $request->request->get('exclude_funded')
            : false;
        $includeVideo = $request->request->has('include_video')
            ? (bool) $request->request->get('include_video')
            : true;
        $includeSecondaryImages = $request->request->has('include_secondary_images')
            ? (bool) $request->request->get('include_secondary_images')
            : true;
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        if ($promptExtra === '') {
            $promptExtra = self::DEFAULT_PROMPT_EXTRA;
        }

        $result = $importService->importProject($ululeId, [
            'lang' => $lang,
            'country' => $country,
            'min_description_length' => $minDescriptionLength,
            'exclude_funded' => $excludeFunded,
            'include_video' => $includeVideo,
            'include_secondary_images' => $includeSecondaryImages,
            'prompt_extra' => $promptExtra,
        ]);

        $extra = $result;
        unset($extra['status'], $extra['message'], $extra['http_status']);

        return $respond(
            $result['status'] ?? 'error',
            $result['message'] ?? '',
            $extra,
            $result['http_status'] ?? Response::HTTP_OK
        );
    }

    private function parseUluleDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractUluleCreatedAt(array $detail): ?\DateTimeImmutable
    {
        return $this->parseUluleDate($detail['date_creation'] ?? null)
            ?? $this->parseUluleDate($detail['date_online'] ?? null)
            ?? $this->parseUluleDate($detail['date_start'] ?? null);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function applyFundingMetadata(array $detail, array $data): array
    {
        $fundingEndAt = $this->extractFundingEndAt($detail);
        if ($fundingEndAt) {
            $data['funding_end_at'] = $fundingEndAt->format(DATE_ATOM);
        }

        $fundingStatus = $this->deriveFundingStatus($detail);
        if ($fundingStatus) {
            $data['funding_status'] = $fundingStatus;
        }

        $data['funding_platform'] = 'Ulule';

        return $data;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractFundingEndAt(array $detail): ?\DateTimeImmutable
    {
        return $this->parseUluleDate($detail['date_end'] ?? null);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function deriveFundingStatus(array $detail): ?string
    {
        $isCancelled = (bool) ($detail['is_cancelled'] ?? false);
        if ($isCancelled) {
            return 'cancelled';
        }

        $goalRaised = $detail['goal_raised'] ?? null;
        if ($goalRaised !== null) {
            $goalRaised = (bool) $goalRaised;
        }
        $finished = (bool) ($detail['finished'] ?? false);
        $isOnline = (bool) ($detail['is_online'] ?? false);
        $fundingEndAt = $this->extractFundingEndAt($detail);
        $now = new \DateTimeImmutable();
        $isPast = $fundingEndAt && $fundingEndAt <= $now;

        $status = $detail['status'] ?? null;
        if (is_string($status)) {
            $status = strtolower(trim($status));
        } else {
            $status = null;
        }

        $statusOngoing = in_array($status, ['online', 'live', 'ongoing', 'funding'], true);
        $statusSuccess = in_array($status, ['success', 'successful', 'funded', 'goal_raised'], true);
        $statusFailed = in_array($status, ['failed', 'failure', 'unfunded'], true);
        $statusEnded = in_array($status, ['ended', 'finished', 'closed'], true);

        if ($finished || $statusEnded || $isPast) {
            if ($goalRaised === true || $statusSuccess) {
                return 'success';
            }
            if ($goalRaised === false || $statusFailed) {
                return 'failed';
            }
            return 'ended';
        }

        if ($isOnline || $statusOngoing || ($fundingEndAt && !$isPast)) {
            return 'ongoing';
        }

        if ($statusSuccess) {
            return 'success';
        }

        if ($statusFailed) {
            return 'failed';
        }

        if ($status === 'cancelled' || $status === 'canceled') {
            return 'cancelled';
        }

        return null;
    }

    #[Route('/admin/ulule/import/project/{ululeId}/preview', name: 'admin_ulule_import_project_preview', methods: ['POST'])]
    public function previewProject(
        int $ululeId,
        Request $request,
        UluleApiClient $ululeApiClient,
        EntityManagerInterface $em,
        string $appNormalizeTextPromptPath,
        string $appScraperModel
    ): Response {
        $lang = trim((string) $request->request->get('lang', 'fr'));
        $country = trim((string) $request->request->get('country', 'FR'));
        $minDescriptionLength = max(0, (int) $request->request->get('min_description_length', 500));
        $excludeFunded = $request->request->has('exclude_funded')
            ? (bool) $request->request->get('exclude_funded')
            : false;
        $includeVideo = $request->request->has('include_video')
            ? (bool) $request->request->get('include_video')
            : true;
        $includeSecondaryImages = $request->request->has('include_secondary_images')
            ? (bool) $request->request->get('include_secondary_images')
            : true;
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        if ($promptExtra === '') {
            $promptExtra = self::DEFAULT_PROMPT_EXTRA;
        }

        $result = [
            'id' => $ululeId,
            'status' => null,
            'reason' => null,
            'error' => null,
        ];

        try {
            $detail = $ululeApiClient->getProject($ululeId, [
                'lang' => $lang,
                'extra_fields' => 'links',
            ]);
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['reason'] = 'detail_fetch_failed';
            $result['error'] = $e->getMessage();
            return $this->json($result, Response::HTTP_BAD_REQUEST);
        }

        $fallbackLang = is_string($detail['lang'] ?? null) ? $detail['lang'] : null;
        $result['name'] = $this->extractI18nString($detail['name'] ?? null, $lang, $fallbackLang);
        $result['url'] = $this->extractSourceUrl($detail, $detail);
        $result['source_url'] = $result['url'];

        if (!$this->isProjectType($detail)) {
            $result['status'] = 'skipped';
            $result['reason'] = 'type_non_project';
            return $this->json($result);
        }

        if ($excludeFunded && ($detail['goal_raised'] ?? false)) {
            $result['status'] = 'skipped';
            $result['reason'] = 'already_funded';
            return $this->json($result);
        }

        if (($detail['is_cancelled'] ?? false) || !($detail['is_online'] ?? true)) {
            $result['status'] = 'skipped';
            $result['reason'] = 'offline_or_cancelled';
            return $this->json($result);
        }

        $description = $this->extractDescription($detail, $lang, $fallbackLang);
        $descriptionLength = $this->plainTextLength($description);
        $result['description_length'] = $descriptionLength;

        if (!empty($result['source_url']) && $this->isDuplicateSourceUrl($em, (string) $result['source_url'])) {
            $result['status'] = 'duplicate';
            $result['reason'] = 'source_url_exists';
            return $this->json($result);
        }

        $secondaryImages = [];
        if ($includeSecondaryImages) {
            try {
                $secondaryImages = $this->fetchSecondaryImages($ululeApiClient, $ululeId, $lang);
            } catch (\Throwable $e) {
                $result['secondary_images_error'] = $e->getMessage();
            }
        }

        $structuredText = $this->buildStructuredText(
            $detail,
            $detail,
            $lang,
            $fallbackLang,
            $result['source_url'],
            $description,
            $secondaryImages,
            $includeVideo
        );
        $result['input'] = $structuredText;

        if ($minDescriptionLength > 0 && $descriptionLength < $minDescriptionLength) {
            $result['status'] = 'insufficient';
            $result['reason'] = 'description_too_short';
            $result['description_min'] = $minDescriptionLength;
            return $this->json($result);
        }

        $prompt = file_get_contents($appNormalizeTextPromptPath);
        if ($prompt === false) {
            $result['status'] = 'error';
            $result['reason'] = 'prompt_missing';
            $result['error'] = 'Prompt introuvable.';
            return $this->json($result, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($promptExtra !== '') {
            $prompt = rtrim($prompt) . "\n\n" . $promptExtra;
        }

        try {
            $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
            $resp = $client->chat()->create([
                'model' => $appScraperModel,
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $structuredText],
                ],
            ]);
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['reason'] = 'openai_failed';
            $result['error'] = $e->getMessage();
            return $this->json($result, Response::HTTP_BAD_REQUEST);
        }

        $content = $resp->choices[0]->message->content ?? '';
        if (!$content) {
            $result['status'] = 'error';
            $result['reason'] = 'empty_response';
            $result['error'] = 'Réponse OpenAI vide.';
            return $this->json($result, Response::HTTP_BAD_REQUEST);
        }

        $result['raw'] = $content;
        $result['enriched'] = $this->buildEnrichedPayload($detail, $content);
        $result['status'] = 'normalized';

        return $this->json($result);
    }

    /**
     * @param array<string, mixed> $project
     */
    private function isProjectType(array $project): bool
    {
        $type = $project['type'] ?? null;
        if (is_string($type)) {
            return $type === 'project';
        }
        if (is_int($type)) {
            return $type === 2;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractSourceUrl(array $detail, array $project): ?string
    {
        $url = $detail['absolute_url'] ?? null;
        if (is_string($url) && $url !== '') {
            return $url;
        }
        $url = $detail['urls']['web']['detail'] ?? null;
        if (is_string($url) && $url !== '') {
            return $url;
        }
        $url = $project['absolute_url'] ?? null;
        if (is_string($url) && $url !== '') {
            return $url;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractDescription(array $detail, string $lang, ?string $fallbackLang): string
    {
        $description = $this->extractI18nString($detail['description'] ?? null, $lang, $fallbackLang);
        if ($description !== null) {
            return $this->normalizeHtmlToText($description);
        }

        $legacy = $this->extractLegacyLangField($detail, 'description', $lang, $fallbackLang);
        if ($legacy !== null) {
            return $this->normalizeHtmlToText($legacy);
        }

        return '';
    }

    private function plainTextLength(string $html): int
    {
        $text = $this->normalizeHtmlToText($html);
        return mb_strlen($text);
    }

    private function normalizeHtmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $text = preg_replace('~<\s*br\s*/?>~i', "\n", $html);
        $text = preg_replace('~</\s*(p|div|li|h[1-6]|section|article|ul|ol|figure|figcaption|blockquote)\s*>~i', "\n", $text ?? '');
        $text = preg_replace('~<\s*li[^>]*>~i', "- ", $text ?? '');
        $text = strip_tags($text ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractLegacyLangField(array $detail, string $base, string $lang, ?string $fallbackLang): ?string
    {
        $legacyKey = $base . '_' . $lang;
        if (isset($detail[$legacyKey]) && is_string($detail[$legacyKey])) {
            $val = trim($detail[$legacyKey]);
            return $val === '' ? null : $val;
        }

        if ($fallbackLang) {
            $legacyFallback = $base . '_' . $fallbackLang;
            if (isset($detail[$legacyFallback]) && is_string($detail[$legacyFallback])) {
                $val = trim($detail[$legacyFallback]);
                return $val === '' ? null : $val;
            }
        }

        return null;
    }

    private function isDuplicateSourceUrl(EntityManagerInterface $em, string $sourceUrl): bool
    {
        $existing = $em->getRepository(PPBase::class)
            ->createQueryBuilder('p')
            ->where('p.ingestion.sourceUrl = :url')
            ->setParameter('url', $sourceUrl)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $existing !== null;
    }

    private function findPresentationBySourceUrl(EntityManagerInterface $em, string $sourceUrl): ?PPBase
    {
        return $em->getRepository(PPBase::class)
            ->createQueryBuilder('p')
            ->where('p.ingestion.sourceUrl = :url')
            ->setParameter('url', $sourceUrl)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array<string, mixed> $detail
     * @param array<string, mixed> $project
     * @param array<int, string> $secondaryImages
     */
    private function buildStructuredText(
        array $detail,
        array $project,
        string $lang,
        ?string $fallbackLang,
        ?string $sourceUrl,
        string $description,
        array $secondaryImages,
        bool $includeVideo
    ): string {
        $name = $this->extractI18nString($detail['name'] ?? null, $lang, $fallbackLang)
            ?? $this->extractLegacyLangField($detail, 'name', $lang, $fallbackLang);
        $subtitle = $this->extractI18nString($detail['subtitle'] ?? null, $lang, $fallbackLang)
            ?? $this->extractLegacyLangField($detail, 'subtitle', $lang, $fallbackLang);
        $descriptionFunding = $this->extractI18nString($detail['description_funding'] ?? null, $lang, $fallbackLang);
        $descriptionYourself = $this->extractI18nString($detail['description_yourself'] ?? null, $lang, $fallbackLang);
        if ($descriptionFunding !== null) {
            $descriptionFunding = $this->normalizeHtmlToText($descriptionFunding);
        }
        if ($descriptionYourself !== null) {
            $descriptionYourself = $this->normalizeHtmlToText($descriptionYourself);
        }

        $location = $this->extractLocationLabel($detail);
        $websiteLinks = $this->extractProjectLinks($detail);

        $mainImageResource = $this->extractI18nResource($detail['main_image'] ?? null, $lang, $fallbackLang);
        $mainImageUrl = $this->extractImageUrl($mainImageResource);
        if (!$mainImageUrl && isset($detail['image']) && is_string($detail['image'])) {
            $mainImageUrl = $detail['image'];
        }

        $videoUrl = null;
        if ($includeVideo) {
            $videoResource = $this->extractI18nResource($detail['video'] ?? null, $lang, $fallbackLang);
            if (is_array($videoResource)) {
                $videoUrl = $videoResource['url'] ?? null;
                if (!is_string($videoUrl) || $videoUrl === '') {
                    $videoUrl = $videoResource['html'] ?? null;
                }
            }
        }

        $lines = [];
        if ($sourceUrl) {
            $lines[] = 'SOURCE URL: ' . $sourceUrl;
        }
        if (!empty($detail['id'])) {
            $lines[] = 'ULULE ID: ' . $detail['id'];
        }
        if ($name) {
            $lines[] = 'NAME: ' . $name;
        } elseif (!empty($project['name'])) {
            $fallbackName = $this->extractI18nString($project['name'], $lang, $fallbackLang);
            if ($fallbackName) {
                $lines[] = 'NAME: ' . $fallbackName;
            }
        }
        if ($subtitle) {
            $lines[] = 'SUBTITLE: ' . $subtitle;
        }
        if ($location) {
            $lines[] = 'LOCATION: ' . $location;
        }
        if ($websiteLinks !== []) {
            $lines[] = 'WEBSITES:';
            foreach ($websiteLinks as $link) {
                $lines[] = sprintf('%s | %s', $link['title'], $link['url']);
            }
        }

        $lines[] = 'DESCRIPTION:';
        $lines[] = $description;

        if ($descriptionFunding) {
            $lines[] = 'DESCRIPTION_FUNDING:';
            $lines[] = $descriptionFunding;
        }
        if ($descriptionYourself) {
            $lines[] = 'DESCRIPTION_YOURSELF:';
            $lines[] = $descriptionYourself;
        }

        if ($mainImageUrl) {
            $lines[] = 'MAIN_IMAGE_URL: ' . $mainImageUrl;
        }

        if ($secondaryImages) {
            $lines[] = 'SECONDARY_IMAGES:';
            foreach ($secondaryImages as $url) {
                $lines[] = $url;
            }
        }

        if ($videoUrl && is_string($videoUrl) && $videoUrl !== '') {
            $lines[] = 'VIDEO_URL: ' . $videoUrl;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function applyUluleEnrichment(array $detail, array $data): array
    {
        $location = $this->extractLocationLabel($detail);
        if ($location) {
            $data['places'] = [$location];
        }

        $thumbnailUrl = $this->extractUluleMainImageUrl($detail);
        if ($thumbnailUrl && (!isset($data['thumbnail_url']) || !is_string($data['thumbnail_url']) || trim($data['thumbnail_url']) === '')) {
            $data['thumbnail_url'] = $thumbnailUrl;
        }
        if ($thumbnailUrl) {
            $data = $this->prependMainImageToSlides($data, $thumbnailUrl);
            $data['keep_thumbnail_in_slides'] = true;
        }

        if (!isset($data['places_default_country']) || !is_string($data['places_default_country']) || trim($data['places_default_country']) === '') {
            $country = $detail['country'] ?? null;
            if (is_string($country)) {
                $country = trim($country);
                if ($country !== '') {
                    $data['places_default_country'] = $country;
                }
            }
        }

        $links = $this->extractProjectLinks($detail);
        $sourceUrl = $this->extractSourceUrl($detail, $detail);
        if ($sourceUrl) {
            if (!isset($data['source_url']) || !is_string($data['source_url']) || trim($data['source_url']) === '') {
                $data['source_url'] = $sourceUrl;
            }
        }

        $mergedWebsites = $this->mergeWebsiteEntries($data['websites'] ?? null, $links);
        if ($sourceUrl) {
            $mergedWebsites = $this->mergeWebsiteEntries(
                [
                    ['title' => 'Page Ulule', 'url' => $sourceUrl],
                ],
                $mergedWebsites
            );
        }
        if ($mergedWebsites !== []) {
            $data['websites'] = $mergedWebsites;
        }

        $data = $this->stripLocationKeywords($data, $location);

        return $data;
    }

    private function prependMainImageToSlides(array $data, string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return $data;
        }

        $imageUrls = [];
        if (isset($data['image_urls']) && is_array($data['image_urls'])) {
            foreach ($data['image_urls'] as $item) {
                if (is_string($item) && $item !== $url) {
                    $imageUrls[] = $item;
                }
            }
        }
        array_unshift($imageUrls, $url);
        $data['image_urls'] = $imageUrls;

        $images = [];
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemUrl = $item['url'] ?? null;
                if (is_string($itemUrl) && $itemUrl === $url) {
                    continue;
                }
                $images[] = $item;
            }
        }
        array_unshift($images, [
            'url' => $url,
            'caption' => null,
            'licence' => 'Copyright Ulule.fr',
        ]);
        $data['images'] = $images;

        return $data;
    }

    private function extractUluleMainImageUrl(array $detail): ?string
    {
        $lang = is_string($detail['lang'] ?? null) ? $detail['lang'] : 'fr';
        $mainImageResource = $this->extractI18nResource($detail['main_image'] ?? null, $lang, null);
        $mainImageUrl = $this->extractImageUrl($mainImageResource);

        if (!$mainImageUrl && isset($detail['image']) && is_string($detail['image']) && trim($detail['image']) !== '') {
            $mainImageUrl = trim($detail['image']);
        }

        return $mainImageUrl ?: null;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractLocationLabel(array $detail): ?string
    {
        $location = $detail['location'] ?? null;
        if (!is_array($location)) {
            return null;
        }

        $city = $this->compactLocationLabel($location['city'] ?? null);
        if ($city) {
            return $city;
        }

        $fullName = $this->compactLocationLabel($location['full_name'] ?? null);
        if ($fullName) {
            return $fullName;
        }

        $name = $this->compactLocationLabel($location['name'] ?? null);
        if ($name) {
            return $name;
        }

        $region = $this->compactLocationLabel($location['region'] ?? null);
        if ($region) {
            return $region;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $detail
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyUluleMetadata(array $detail, array $data): array
    {
        $data = $this->applyFundingMetadata($detail, $data);
        return $this->applyUluleEnrichment($detail, $data);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function buildEnrichedPayload(array $detail, string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $this->applyUluleMetadata($detail, $decoded);
    }

    private function compactLocationLabel(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $value));
        foreach ($parts as $part) {
            if ($part !== '') {
                return $part;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $detail
     * @return array<int, array{title:string,url:string}>
     */
    private function extractProjectLinks(array $detail): array
    {
        $links = $detail['links'] ?? null;
        if (!is_array($links)) {
            return [];
        }

        $items = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = $link['url'] ?? null;
            if (!is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $title = $this->guessWebsiteTitle($url);
            if ($title === null) {
                continue;
            }

            $items[] = [
                'title' => $title,
                'url' => $url,
            ];
        }

        return $this->mergeWebsiteEntries([], $items);
    }

    private function guessWebsiteTitle(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host ?? '');
        if ($host === '') {
            return null;
        }

        if (str_ends_with($host, 'ulule.com')) {
            return null;
        }

        $map = [
            'facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'twitter.com' => 'X',
            'x.com' => 'X',
            'linkedin.com' => 'LinkedIn',
            'youtube.com' => 'YouTube',
            'youtu.be' => 'YouTube',
            'tiktok.com' => 'TikTok',
            'pinterest.com' => 'Pinterest',
            'snapchat.com' => 'Snapchat',
            'discord.gg' => 'Discord',
            'discord.com' => 'Discord',
            'twitch.tv' => 'Twitch',
            'github.com' => 'GitHub',
            'gitlab.com' => 'GitLab',
            'medium.com' => 'Medium',
        ];

        foreach ($map as $suffix => $label) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return $label;
            }
        }

        return $host;
    }

    /**
     * @param mixed $existing
     * @param array<int, array{title:string,url:string}> $incoming
     * @return array<int, array{title:string,url:string}>
     */
    private function mergeWebsiteEntries(mixed $existing, array $incoming): array
    {
        $merged = [];
        $seen = [];

        if (is_array($existing)) {
            foreach ($existing as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $url = $entry['url'] ?? null;
                $title = $entry['title'] ?? null;
                if (!is_string($url) || $url === '' || !is_string($title) || $title === '') {
                    continue;
                }
                $key = $this->normalizeUrlKey($url);
                $merged[] = ['title' => $title, 'url' => $url];
                $seen[$key] = true;
            }
        }

        foreach ($incoming as $entry) {
            $url = $entry['url'] ?? null;
            $title = $entry['title'] ?? null;
            if (!is_string($url) || $url === '' || !is_string($title) || $title === '') {
                continue;
            }
            $key = $this->normalizeUrlKey($url);
            if (isset($seen[$key])) {
                continue;
            }
            $merged[] = ['title' => $title, 'url' => $url];
            $seen[$key] = true;
        }

        return $merged;
    }

    private function normalizeUrlKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return strtolower($url);
        }

        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';
        $path = is_string($path) ? rtrim($path, '/') : '';

        return $host . $path;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stripLocationKeywords(array $data, ?string $location): array
    {
        if (!$location) {
            return $data;
        }

        $keywords = $data['keywords'] ?? null;
        if (!is_array($keywords) || $keywords === []) {
            return $data;
        }

        $title = $this->stringValue($data['title'] ?? null) ?? '';
        $locationTokens = $this->extractLocationTokens($location);
        $normalizedLocation = $this->normalizeForMatch($location);

        if ($title !== '' && $this->titleMentionsLocation($title, $normalizedLocation, $locationTokens)) {
            return $data;
        }

        $filtered = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $normalizedKeyword = $this->normalizeForMatch($keyword);
            if ($normalizedKeyword === '') {
                continue;
            }

            $isLocation = false;
            if ($normalizedLocation !== '' && str_contains($normalizedKeyword, $normalizedLocation)) {
                $isLocation = true;
            }

            if (!$isLocation) {
                foreach ($locationTokens as $token) {
                    if ($token !== '' && str_contains($normalizedKeyword, $token)) {
                        $isLocation = true;
                        break;
                    }
                }
            }

            if ($isLocation) {
                continue;
            }

            $filtered[] = $keyword;
        }

        $data['keywords'] = array_values($filtered);

        return $data;
    }

    /**
     * @return string[]
     */
    private function extractLocationTokens(string $location): array
    {
        $normalized = $this->normalizeForMatch($location);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 3) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param string[] $locationTokens
     */
    private function titleMentionsLocation(string $title, string $normalizedLocation, array $locationTokens): bool
    {
        $normalizedTitle = $this->normalizeForMatch($title);
        if ($normalizedTitle === '') {
            return false;
        }

        if ($normalizedLocation !== '' && str_contains($normalizedTitle, $normalizedLocation)) {
            return true;
        }

        foreach ($locationTokens as $token) {
            if ($token !== '' && str_contains($normalizedTitle, $token)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForMatch(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = $this->stripAccents($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function stripAccents(string $value): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated === false) {
            return $value;
        }

        return $transliterated;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function extractImageUrl(?array $resource): ?string
    {
        if (!$resource) {
            return null;
        }

        if (isset($resource['versions']['full']['url']) && is_string($resource['versions']['full']['url'])) {
            return $resource['versions']['full']['url'];
        }
        if (isset($resource['full']) && is_string($resource['full'])) {
            return $resource['full'];
        }
        if (isset($resource['versions']['large']['url']) && is_string($resource['versions']['large']['url'])) {
            return $resource['versions']['large']['url'];
        }
        if (isset($resource['url']) && is_string($resource['url'])) {
            return $resource['url'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function fetchSecondaryImages(UluleApiClient $client, int $projectId, string $lang): array
    {
        $images = $client->getProjectImages($projectId, ['limit' => 20, 'lang' => $lang]);
        $items = $images['images'] ?? $images['project_images'] ?? $images['items'] ?? [];

        $urls = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['type'] ?? null) !== 'secondary') {
                continue;
            }
            $url = $this->extractImageUrl($item);
            if ($url) {
                $urls[] = $url;
            }
            if (count($urls) >= self::MAX_SECONDARY_IMAGES) {
                break;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param mixed $value
     */
    private function extractI18nString(mixed $value, string $lang, ?string $fallbackLang): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        if (!is_array($value)) {
            return null;
        }

        if (isset($value[$lang]) && is_string($value[$lang])) {
            $val = trim($value[$lang]);
            if ($val !== '') {
                return $val;
            }
        }

        if ($fallbackLang && isset($value[$fallbackLang]) && is_string($value[$fallbackLang])) {
            $val = trim($value[$fallbackLang]);
            if ($val !== '') {
                return $val;
            }
        }

        foreach ($value as $val) {
            if (is_string($val)) {
                $val = trim($val);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|null
     */
    private function extractI18nResource(mixed $value, string $lang, ?string $fallbackLang): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (isset($value[$lang]) && is_array($value[$lang])) {
            return $value[$lang];
        }

        if ($fallbackLang && isset($value[$fallbackLang]) && is_array($value[$fallbackLang])) {
            return $value[$fallbackLang];
        }

        foreach ($value as $val) {
            if (is_array($val)) {
                return $val;
            }
        }

        if (isset($value['url']) || isset($value['versions']) || isset($value['full'])) {
            return $value;
        }

        return null;
    }

}
