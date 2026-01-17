<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use App\Service\NormalizedProjectPersister;
use App\Service\ScraperUserResolver;
use App\Service\UluleApiClient;
use OpenAI;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;

#[Route('/admin/ulule/import', name: 'admin_ulule_import', methods: ['GET', 'POST'])]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class UluleImportController extends AbstractController
{
    private const PER_PAGE = 20;
    private const MAX_PAGES = 10;
    private const MAX_SECONDARY_IMAGES = 5;
    private const DEFAULT_PROMPT_EXTRA = 'Ce complément de prompt définit des instructions hautement prioritaires par rapport aux précédentes : Pour chaque image remplit le champ licence avec "Copyright Ulule.fr"';

    public function __invoke(
        Request $request,
        UluleApiClient $ululeApiClient,
        NormalizedProjectPersister $persister,
        ScraperUserResolver $scraperUserResolver,
        EntityManagerInterface $em,
        string $appNormalizeTextPromptPath,
        string $appScraperModel
    ): Response {
        $lang = trim((string) $request->request->get('lang', 'fr'));
        $country = trim((string) $request->request->get('country', 'FR'));
        $status = trim((string) $request->request->get('status', 'currently'));
        $sort = trim((string) $request->request->get('sort', 'new'));
        $pageStart = max(1, (int) $request->request->get('page_start', 1));
        $pageCount = max(1, (int) $request->request->get('page_count', 1));
        $pageCount = min(self::MAX_PAGES, $pageCount);
        $minDescriptionLength = max(0, (int) $request->request->get('min_description_length', 500));
        $excludeFunded = $request->request->has('exclude_funded')
            ? (bool) $request->request->get('exclude_funded')
            : true;
        $includeVideo = $request->request->has('include_video')
            ? (bool) $request->request->get('include_video')
            : true;
        $includeSecondaryImages = $request->request->has('include_secondary_images')
            ? (bool) $request->request->get('include_secondary_images')
            : false;
        $persist = (bool) $request->request->get('persist', false);
        $extraQuery = trim((string) $request->request->get('extra_query', ''));
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        if ($promptExtra === '' && !$request->isMethod('POST')) {
            $promptExtra = self::DEFAULT_PROMPT_EXTRA;
        }

        $results = [];
        $summary = [
            'total' => 0,
            'created' => 0,
            'normalized' => 0,
            'duplicates' => 0,
            'insufficient' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

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

        if ($request->isMethod('POST')) {
            $prompt = file_get_contents($appNormalizeTextPromptPath);
            if ($prompt === false) {
                $results[] = ['error' => 'Prompt introuvable.'];
            } else {
                if ($promptExtra !== '') {
                    $prompt = rtrim($prompt) . "\n\n" . $promptExtra;
                }
                $client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');
                $queryString = $this->buildQueryString($lang, $country, $status, $sort, $extraQuery);

                for ($page = 0; $page < $pageCount; $page++) {
                    $offset = ($pageStart - 1 + $page) * self::PER_PAGE;
                    try {
                        $search = $ululeApiClient->searchProjects([
                            'lang' => $lang,
                            'limit' => self::PER_PAGE,
                            'offset' => $offset,
                            'q' => $queryString,
                        ]);
                    } catch (\Throwable $e) {
                        $summary['errors']++;
                        $results[] = [
                            'error' => sprintf('Erreur search Ulule (page %d): %s', $pageStart + $page, $e->getMessage()),
                        ];
                        continue;
                    }

                    $projects = $search['projects'] ?? [];
                    foreach ($projects as $project) {
                        $summary['total']++;
                        $entry = $this->buildBaseResult($project, $lang);

                        if (!$this->isProjectType($project)) {
                            $entry['status'] = 'skipped';
                            $entry['reason'] = 'type_non_project';
                            $summary['skipped']++;
                            $results[] = $entry;
                            continue;
                        }

                        $projectId = $entry['id'] ?? null;
                        if (!$projectId) {
                            $entry['status'] = 'error';
                            $entry['reason'] = 'missing_id';
                            $summary['errors']++;
                            $results[] = $entry;
                            continue;
                        }

                        try {
                            $detail = $ululeApiClient->getProject((int) $projectId, [
                                'lang' => $lang,
                            ]);
                        } catch (\Throwable $e) {
                            $entry['status'] = 'error';
                            $entry['reason'] = 'detail_fetch_failed';
                            $entry['error'] = $e->getMessage();
                            $summary['errors']++;
                            $results[] = $entry;
                            continue;
                        }

                        if ($excludeFunded && ($detail['goal_raised'] ?? false)) {
                            $entry['status'] = 'skipped';
                            $entry['reason'] = 'already_funded';
                            $summary['skipped']++;
                            $results[] = $entry;
                            continue;
                        }

                        if (($detail['is_cancelled'] ?? false) || !($detail['is_online'] ?? true)) {
                            $entry['status'] = 'skipped';
                            $entry['reason'] = 'offline_or_cancelled';
                            $summary['skipped']++;
                            $results[] = $entry;
                            continue;
                        }

                        $fallbackLang = is_string($detail['lang'] ?? null) ? $detail['lang'] : null;
                        $description = $this->extractDescription($detail, $lang, $fallbackLang);
                        $descriptionLength = $this->plainTextLength($description);
                        $entry['description_length'] = $descriptionLength;

                        if ($minDescriptionLength > 0 && $descriptionLength < $minDescriptionLength) {
                            $entry['status'] = 'insufficient';
                            $entry['reason'] = 'description_too_short';
                            $entry['description_min'] = $minDescriptionLength;
                            $summary['insufficient']++;
                            $results[] = $entry;
                            continue;
                        }

                        $sourceUrl = $this->extractSourceUrl($detail, $project);
                        $entry['source_url'] = $sourceUrl;
                        if ($sourceUrl) {
                            $entry['url'] = $sourceUrl;
                        }

                        if ($sourceUrl && $this->isDuplicateSourceUrl($em, $sourceUrl)) {
                            $entry['status'] = 'duplicate';
                            $entry['reason'] = 'source_url_exists';
                            $summary['duplicates']++;
                            $results[] = $entry;
                            continue;
                        }

                        $secondaryImages = [];
                        if ($includeSecondaryImages) {
                            try {
                                $secondaryImages = $this->fetchSecondaryImages($ululeApiClient, (int) $projectId, $lang);
                            } catch (\Throwable $e) {
                                $entry['secondary_images_error'] = $e->getMessage();
                            }
                        }

                        $structuredText = $this->buildStructuredText(
                            $detail,
                            $project,
                            $lang,
                            $fallbackLang,
                            $sourceUrl,
                            $description,
                            $secondaryImages,
                            $includeVideo
                        );

                        $entry['input'] = $structuredText;

                        try {
                            $resp = $client->chat()->create([
                                'model' => $appScraperModel,
                                'temperature' => 0.3,
                                'messages' => [
                                    ['role' => 'system', 'content' => $prompt],
                                    ['role' => 'user', 'content' => $structuredText],
                                ],
                            ]);
                        } catch (\Throwable $e) {
                            $entry['status'] = 'error';
                            $entry['reason'] = 'openai_failed';
                            $entry['error'] = $e->getMessage();
                            $summary['errors']++;
                            $results[] = $entry;
                            continue;
                        }

                        $content = $resp->choices[0]->message->content ?? '';
                        $entry['raw'] = $content;
                        $summary['normalized']++;

                        if ($persist && $content && $creator) {
                            try {
                                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                                $created = $persister->persist($data, $creator);
                                $entry['created'] = $created;
                                $entry['status'] = 'created';
                                $summary['created']++;
                            } catch (\Throwable $e) {
                                $entry['status'] = 'error';
                                $entry['reason'] = 'persist_failed';
                                $entry['error'] = $e->getMessage();
                                $summary['errors']++;
                            }
                        } else {
                            $entry['status'] = 'normalized';
                        }

                        $results[] = $entry;
                    }
                }
            }
        }

        return $this->render('admin/ulule_import.html.twig', [
            'lang' => $lang,
            'country' => $country,
            'status' => $status,
            'sort' => $sort,
            'pageStart' => $pageStart,
            'pageCount' => $pageCount,
            'minDescriptionLength' => $minDescriptionLength,
            'excludeFunded' => $excludeFunded,
            'includeVideo' => $includeVideo,
            'includeSecondaryImages' => $includeSecondaryImages,
            'persist' => $persist,
            'extraQuery' => $extraQuery,
            'promptExtra' => $promptExtra,
            'results' => $results,
            'summary' => $summary,
        ]);
    }

    private function buildQueryString(string $lang, string $country, string $status, string $sort, string $extraQuery): string
    {
        $parts = [];
        if ($lang !== '') {
            $parts[] = sprintf('lang:%s', $lang);
        }
        if ($country !== '') {
            $parts[] = sprintf('country:%s', $country);
        }
        if ($status !== '') {
            $parts[] = sprintf('status:%s', $status);
        }
        if ($sort !== '') {
            $parts[] = sprintf('sort:%s', $sort);
        }
        if ($extraQuery !== '') {
            $parts[] = $extraQuery;
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function buildBaseResult(array $project, string $lang): array
    {
        return [
            'id' => $project['id'] ?? null,
            'slug' => $project['slug'] ?? null,
            'url' => $project['absolute_url'] ?? null,
            'name' => $this->extractI18nString($project['name'] ?? null, $lang, null),
            'subtitle' => $this->extractI18nString($project['subtitle'] ?? null, $lang, null),
        ];
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
            return $description;
        }

        $legacy = $this->extractLegacyLangField($detail, 'description', $lang, $fallbackLang);
        if ($legacy !== null) {
            return $legacy;
        }

        return '';
    }

    private function plainTextLength(string $html): int
    {
        $text = trim(strip_tags($html));
        return mb_strlen($text);
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

        $location = $detail['location']['full_name'] ?? $detail['location']['city'] ?? null;

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
