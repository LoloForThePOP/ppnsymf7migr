<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use App\Entity\UluleProjectCatalog;
use App\Repository\UluleProjectCatalogRepository;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class UluleImportController extends AbstractController
{
    private const PER_PAGE = 20;
    private const MAX_PAGES = 10;
    private const MAX_SECONDARY_IMAGES = 5;
    private const DEFAULT_PROMPT_EXTRA = 'Ce complément de prompt définit des instructions hautement prioritaires par rapport aux précédentes : Pour chaque image remplit le champ licence avec "Copyright Ulule.fr"';

    #[Route('/admin/ulule/import', name: 'admin_ulule_import', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UluleApiClient $ululeApiClient,
        NormalizedProjectPersister $persister,
        ScraperUserResolver $scraperUserResolver,
        EntityManagerInterface $em,
        string $appNormalizeTextPromptPath,
        string $appScraperModel
    ): Response {
        $responseFormat = trim((string) $request->request->get('response_format', ''));
        $isJson = $responseFormat === 'json';
        $lang = trim((string) $request->request->get('lang', 'fr'));
        $country = trim((string) $request->request->get('country', 'FR'));
        $status = trim((string) $request->request->get('status', 'currently'));
        $sort = trim((string) $request->request->get('sort', 'new'));
        $pageStart = max(1, (int) $request->request->get('page_start', 1));
        $pageCount = max(1, (int) $request->request->get('page_count', 1));
        $pageCount = min(self::MAX_PAGES, $pageCount);
        $minDescriptionLength = max(0, (int) $request->request->get('min_description_length', 500));
        $itemOffset = max(0, (int) $request->request->get('item_offset', 0));
        $itemLimit = max(0, (int) $request->request->get('item_limit', 0));
        $excludeFunded = $request->request->has('exclude_funded')
            ? (bool) $request->request->get('exclude_funded')
            : false;
        $includeVideo = $request->request->has('include_video')
            ? (bool) $request->request->get('include_video')
            : true;
        $includeSecondaryImages = $request->request->has('include_secondary_images')
            ? (bool) $request->request->get('include_secondary_images')
            : true;
        $persist = (bool) $request->request->get('persist', false);
        $extraQuery = trim((string) $request->request->get('extra_query', ''));
        $promptExtra = trim((string) $request->request->get('prompt_extra', ''));
        if ($promptExtra === '' && !$request->isMethod('POST')) {
            $promptExtra = self::DEFAULT_PROMPT_EXTRA;
        }
        $includeDebug = (bool) $request->request->get('include_debug', false);

        $pageSize = null;
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
            if ($isJson) {
                $pageCount = 1;
            }

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
                    if ($pageSize === null && $isJson) {
                        $pageSize = count($projects);
                    }

                    $projectCount = count($projects);
                    $startIndex = min($itemOffset, $projectCount);
                    $endIndex = $projectCount;
                    if ($itemLimit > 0) {
                        $endIndex = min($projectCount, $itemOffset + $itemLimit);
                    }

                    for ($index = $startIndex; $index < $endIndex; $index++) {
                        $project = $projects[$index];
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
                                $data = $this->applyFundingMetadata($detail, $data);
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

        if ($isJson) {
            return $this->json([
                'page_start' => $pageStart,
                'page_count' => $pageCount,
                'page_size' => $pageSize ?? 0,
                'item_offset' => $itemOffset,
                'item_limit' => $itemLimit,
                'summary' => $summary,
                'results' => $this->normalizeResultsForJson($results, $includeDebug),
            ]);
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

    #[Route('/admin/ulule/import/project/{ululeId}', name: 'admin_ulule_import_project', methods: ['POST'])]
    public function importProject(
        int $ululeId,
        Request $request,
        UluleApiClient $ululeApiClient,
        NormalizedProjectPersister $persister,
        ScraperUserResolver $scraperUserResolver,
        EntityManagerInterface $em,
        UluleProjectCatalogRepository $catalogRepository,
        string $appNormalizeTextPromptPath,
        string $appScraperModel
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

        $creator = $scraperUserResolver->resolve();
        if (!$creator) {
            return $respond(
                'error',
                sprintf('Compte "%s" introuvable ou multiple. Import annulé.', $scraperUserResolver->getRole()),
                [],
                Response::HTTP_BAD_REQUEST
            );
        }

        $catalogEntry = $catalogRepository->findOneByUluleId($ululeId);
        if (!$catalogEntry) {
            $catalogEntry = new UluleProjectCatalog($ululeId);
            $em->persist($catalogEntry);
        }

        try {
            $detail = $ululeApiClient->getProject($ululeId, [
                'lang' => $lang,
            ]);
        } catch (\Throwable $e) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_FAILED);
            $catalogEntry->setImportStatusComment('detail_fetch_failed');
            $catalogEntry->setLastError($e->getMessage());
            $em->flush();
            return $respond(
                'error',
                sprintf('Erreur Ulule: %s', $e->getMessage()),
                ['reason' => 'detail_fetch_failed'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->isProjectType($detail)) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_SKIPPED);
            $catalogEntry->setImportStatusComment('type_non_project');
            $em->flush();
            return $respond('skipped', 'Projet Ulule ignoré (type non project).', ['reason' => 'type_non_project']);
        }

        if ($excludeFunded && ($detail['goal_raised'] ?? false)) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_SKIPPED);
            $catalogEntry->setImportStatusComment('already_funded');
            $em->flush();
            return $respond('skipped', 'Projet déjà financé, import ignoré.', ['reason' => 'already_funded']);
        }

        if (($detail['is_cancelled'] ?? false) || !($detail['is_online'] ?? true)) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_SKIPPED);
            $catalogEntry->setImportStatusComment('offline_or_cancelled');
            $em->flush();
            return $respond('skipped', 'Projet hors ligne ou annulé, import ignoré.', ['reason' => 'offline_or_cancelled']);
        }

        $fallbackLang = is_string($detail['lang'] ?? null) ? $detail['lang'] : null;
        $description = $this->extractDescription($detail, $lang, $fallbackLang);
        $descriptionLength = $this->plainTextLength($description);

        $sourceUrl = $this->extractSourceUrl($detail, $detail);
        if ($sourceUrl) {
            $existing = $this->findPresentationBySourceUrl($em, $sourceUrl);
            if ($existing) {
                $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_IMPORTED);
                $catalogEntry->setImportStatusComment('source_url_exists');
                $catalogEntry->setSourceUrl($sourceUrl);
                $catalogEntry->setImportedStringId($existing->getStringId());
                $em->flush();
                $createdUrl = $this->generateUrl(
                    'edit_show_project_presentation',
                    ['stringId' => $existing->getStringId()],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                );
                return $respond(
                    'duplicate',
                    'Projet déjà importé (source URL existante).',
                    [
                        'reason' => 'source_url_exists',
                        'created_url' => $createdUrl,
                        'created_string_id' => $existing->getStringId(),
                    ]
                );
            }
        }

        if ($minDescriptionLength > 0 && $descriptionLength < $minDescriptionLength) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_SKIPPED);
            $catalogEntry->setImportStatusComment('description_too_short');
            $catalogEntry->setDescriptionLength($descriptionLength);
            $em->flush();
            return $respond('skipped', 'Description trop courte, import ignoré.', ['reason' => 'description_too_short']);
        }

        $catalogEntry->setName($this->extractI18nString($detail['name'] ?? null, $lang, $fallbackLang));
        $catalogEntry->setSubtitle($this->extractI18nString($detail['subtitle'] ?? null, $lang, $fallbackLang));
        $catalogEntry->setSlug(is_string($detail['slug'] ?? null) ? $detail['slug'] : null);
        $catalogEntry->setSourceUrl($sourceUrl);
        $catalogEntry->setLang(is_string($detail['lang'] ?? null) ? $detail['lang'] : $lang);
        $catalogEntry->setCountry(is_string($detail['country'] ?? null) ? $detail['country'] : $country);
        $catalogEntry->setType(
            is_string($detail['type'] ?? null)
                ? $detail['type']
                : (is_int($detail['type'] ?? null) ? (string) $detail['type'] : null)
        );
        $catalogEntry->setGoalRaised(isset($detail['goal_raised']) ? (bool) $detail['goal_raised'] : null);
        $catalogEntry->setIsOnline(isset($detail['is_online']) ? (bool) $detail['is_online'] : null);
        $catalogEntry->setIsCancelled(isset($detail['is_cancelled']) ? (bool) $detail['is_cancelled'] : null);
        $catalogEntry->setDescriptionLength($descriptionLength);
        $catalogEntry->setUluleCreatedAt($this->extractUluleCreatedAt($detail));
        $catalogEntry->setLastSeenAt(new \DateTimeImmutable());
        $catalogEntry->setLastError(null);

        $secondaryImages = [];
        if ($includeSecondaryImages) {
            try {
                $secondaryImages = $this->fetchSecondaryImages($ululeApiClient, $ululeId, $lang);
            } catch (\Throwable $e) {
                $catalogEntry->setLastError($e->getMessage());
            }
        }

        $structuredText = $this->buildStructuredText(
            $detail,
            $detail,
            $lang,
            $fallbackLang,
            $sourceUrl,
            $description,
            $secondaryImages,
            $includeVideo
        );

        $prompt = file_get_contents($appNormalizeTextPromptPath);
        if ($prompt === false) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_FAILED);
            $catalogEntry->setImportStatusComment('prompt_missing');
            $em->flush();
            return $respond('error', 'Prompt introuvable.', ['reason' => 'prompt_missing'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_FAILED);
            $catalogEntry->setImportStatusComment('openai_failed');
            $catalogEntry->setLastError($e->getMessage());
            $em->flush();
            return $respond(
                'error',
                sprintf('Erreur OpenAI: %s', $e->getMessage()),
                ['reason' => 'openai_failed'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $content = $resp->choices[0]->message->content ?? '';
        if (!$content) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_FAILED);
            $catalogEntry->setImportStatusComment('empty_response');
            $em->flush();
            return $respond('error', 'Réponse OpenAI vide.', ['reason' => 'empty_response'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $data = $this->applyFundingMetadata($detail, $data);
            $created = $persister->persist($data, $creator);
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_IMPORTED);
            $catalogEntry->setImportStatusComment(null);
            $catalogEntry->setImportedStringId($created->getStringId());
            $catalogEntry->setLastImportedAt(new \DateTimeImmutable());
            $catalogEntry->setLastError(null);
            $em->flush();
            $createdUrl = $this->generateUrl(
                'edit_show_project_presentation',
                ['stringId' => $created->getStringId()],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
            return $respond('imported', 'Projet importé.', [
                'created_url' => $createdUrl,
                'created_string_id' => $created->getStringId(),
            ]);
        } catch (\Throwable $e) {
            $catalogEntry->setImportStatus(UluleProjectCatalog::STATUS_FAILED);
            $catalogEntry->setImportStatusComment('persist_failed');
            $catalogEntry->setLastError($e->getMessage());
            $em->flush();
            return $respond(
                'error',
                sprintf('Erreur persistance: %s', $e->getMessage()),
                ['reason' => 'persist_failed'],
                Response::HTTP_BAD_REQUEST
            );
        }
        return $respond('error', 'Import interrompu.', ['reason' => 'unexpected'], Response::HTTP_BAD_REQUEST);
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

        $goalRaised = (bool) ($detail['goal_raised'] ?? false);
        $finished = (bool) ($detail['finished'] ?? false);
        $isOnline = (bool) ($detail['is_online'] ?? false);

        if ($finished) {
            return $goalRaised ? 'success' : 'failed';
        }

        if ($goalRaised) {
            return 'success';
        }

        if ($isOnline) {
            return 'ongoing';
        }

        $status = $detail['status'] ?? null;
        if (is_string($status)) {
            $status = strtolower(trim($status));
            return match ($status) {
                'online', 'live', 'ongoing', 'funding' => 'ongoing',
                'success', 'successful', 'funded', 'goal_raised' => 'success',
                'failed', 'failure', 'unfunded' => 'failed',
                'cancelled', 'canceled' => 'cancelled',
                'ended', 'finished', 'closed' => 'ended',
                default => null,
            };
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
        $result['status'] = 'normalized';

        return $this->json($result);
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

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResultsForJson(array $results, bool $includeDebug): array
    {
        $normalized = [];

        foreach ($results as $item) {
            $entry = [
                'id' => $item['id'] ?? null,
                'slug' => $item['slug'] ?? null,
                'name' => $item['name'] ?? null,
                'url' => $item['url'] ?? null,
                'source_url' => $item['source_url'] ?? null,
                'status' => $item['status'] ?? null,
                'reason' => $item['reason'] ?? null,
                'error' => $item['error'] ?? null,
                'description_length' => $item['description_length'] ?? null,
                'description_min' => $item['description_min'] ?? null,
            ];

            if ($includeDebug) {
                $entry['input'] = $item['input'] ?? null;
                $entry['raw'] = $item['raw'] ?? null;
            }

            if (isset($item['created']) && $item['created'] instanceof PPBase) {
                $entry['created'] = [
                    'stringId' => $item['created']->getStringId(),
                    'title' => $item['created']->getTitle(),
                    'goal' => $item['created']->getGoal(),
                    'url' => $this->generateUrl(
                        'edit_show_project_presentation',
                        ['stringId' => $item['created']->getStringId()],
                        UrlGeneratorInterface::ABSOLUTE_PATH
                    ),
                ];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }
}
