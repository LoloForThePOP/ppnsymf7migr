<?php

namespace App\Controller\Admin;

use App\Controller\SafeRefererRedirectTrait;
use App\Entity\PPBase;
use App\Entity\UluleProjectCatalog;
use App\Repository\UluleProjectCatalogRepository;
use App\Service\Scraping\Ulule\UluleApiClient;
use App\Service\Scraping\Common\WorkerHeartbeatService;
use App\Security\Voter\ScraperAccessVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class UluleCatalogController extends AbstractController
{
    use SafeRefererRedirectTrait;

    private const PER_PAGE = 20;
    private const MAX_PAGES = 50;
    private const LATEST_REFRESH_PAGE_COUNT = 3;
    private const DEFAULT_PROMPT_EXTRA = 'Ce complément de prompt définit des instructions hautement prioritaires par rapport aux précédentes : Pour chaque image remplit le champ licence avec "Copyright Ulule.fr". N\'inclue pas la localisation (ville/commune/région/pays) dans les keywords, sauf si la localisation fait partie du titre du projet. Pour goal, évite toute sémantique de collecte/soutien/financement (ex: "soutenir", "collecter", "financer") et formule l\'objectif comme la réalisation concrète du projet (ex: "Produire…", "Réaliser…", "Créer…").';

    #[Route('/admin/ulule/catalog', name: 'admin_ulule_catalog', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UluleProjectCatalogRepository $catalogRepository,
        \App\Service\Scraping\Ulule\UluleCatalogRefresher $catalogRefresher,
        \App\Service\Scraping\Ulule\UluleQueueStateService $queueStateService,
        WorkerHeartbeatService $workerHeartbeat
    ): Response {
        $input = $request->isMethod('POST') ? $request->request : $request->query;
        $lang = trim((string) $input->get('lang', 'fr'));
        $country = trim((string) $input->get('country', 'FR'));
        $status = trim((string) $input->get('status', 'currently'));
        $sort = trim((string) $input->get('sort', 'new'));
        $pageStart = max(1, (int) $input->get('page_start', 1));
        $pageCount = max(1, (int) $input->get('page_count', 10));
        $pageCount = min(self::MAX_PAGES, $pageCount);
        $refreshLatest = $request->isMethod('POST') && $request->request->has('refresh_latest');
        if ($refreshLatest) {
            // Dedicated refresh mode focused on newest active campaigns.
            $status = 'currently';
            $sort = 'new';
            $pageStart = 1;
            $pageCount = self::LATEST_REFRESH_PAGE_COUNT;
        }
        $minDescriptionLength = max(0, (int) $input->get('min_description_length', 500));
        $excludeFunded = $input->has('exclude_funded')
            ? (bool) $input->get('exclude_funded')
            : false;
        $includeVideo = $input->has('include_video')
            ? (bool) $input->get('include_video')
            : true;
        $includeSecondaryImages = $input->has('include_secondary_images')
            ? (bool) $input->get('include_secondary_images')
            : true;
        $extraQuery = trim((string) $input->get('extra_query', ''));
        $promptExtra = trim((string) $input->get('prompt_extra', ''));
        if ($promptExtra === '') {
            $savedPrompt = trim((string) ($queueStateService->readState()['filters']['prompt_extra'] ?? ''));
            $promptExtra = $savedPrompt !== '' ? $savedPrompt : self::DEFAULT_PROMPT_EXTRA;
        }

        $statusFilter = trim((string) $input->get('status_filter', UluleProjectCatalog::STATUS_PENDING));
        $eligibleOnlyParam = $input->get('eligible_only');
        if (is_array($eligibleOnlyParam)) {
            $eligibleOnlyParam = end($eligibleOnlyParam);
        }
        if ($eligibleOnlyParam === null) {
            $eligibleOnly = true;
        } else {
            $eligibleOnly = filter_var($eligibleOnlyParam, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_ulule_catalog', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('admin_ulule_catalog');
            }
        }

        if ($request->isMethod('POST') && $request->request->has('save_prompt')) {
            $queueStateService->writeState([
                'filters' => [
                    'prompt_extra' => $promptExtra,
                ],
            ]);
            $this->addFlash('success', 'Complément de prompt enregistré.');
            return $this->redirect($this->generateUrl('admin_ulule_catalog'));
        }

        $refreshSummary = null;
        if ($request->isMethod('POST')) {
            $refreshSummary = $catalogRefresher->refreshCatalog(
                $lang,
                $country,
                $status,
                $sort,
                $pageStart,
                $pageCount,
                $extraQuery
            );
            foreach ($refreshSummary['error_messages'] ?? [] as $message) {
                $this->addFlash('danger', $message);
            }
        }

        $items = $this->loadItems($catalogRepository, $statusFilter);
        if ($eligibleOnly) {
            $items = array_values(array_filter(
                $items,
                fn (UluleProjectCatalog $item) => $this->isEligible($item, $minDescriptionLength, $excludeFunded)
            ));
        }

        $now = new \DateTimeImmutable();
        $lastSeenLabels = [];
        $lastImportedLabels = [];
        $ululeCreatedLabels = [];
        foreach ($items as $item) {
            $lastSeenLabels[$item->getUluleId()] = $this->formatRelativeDate($item->getLastSeenAt(), $now);
            $lastImportedLabels[$item->getUluleId()] = $this->formatRelativeDate($item->getLastImportedAt(), $now);
            $ululeCreatedLabels[$item->getUluleId()] = $this->formatRelativeDate($item->getUluleCreatedAt(), $now);
        }

        return $this->render('admin/ulule_catalog.html.twig', [
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
            'extraQuery' => $extraQuery,
            'promptExtra' => $promptExtra,
            'statusFilter' => $statusFilter,
            'eligibleOnly' => $eligibleOnly,
            'items' => $items,
            'statusCounts' => $catalogRepository->getStatusCounts(),
            'refreshSummary' => $refreshSummary,
            'lastSeenLabels' => $lastSeenLabels,
            'lastImportedLabels' => $lastImportedLabels,
            'ululeCreatedLabels' => $ululeCreatedLabels,
            'ululeQueueState' => $queueStateService->readState()['queue'],
            'workerStatus' => $workerHeartbeat->getStatus(),
            'workerCommand' => 'php bin/console messenger:consume async -vv',
        ]);
    }

    #[Route('/admin/ulule/catalog/{ululeId}/status', name: 'admin_ulule_catalog_status', methods: ['POST'])]
    public function updateStatus(
        int $ululeId,
        Request $request,
        UluleProjectCatalogRepository $catalogRepository,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_ulule_catalog_status', $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToSafeReferer($request, 'admin_ulule_catalog');
        }

        $status = trim((string) $request->request->get('status', ''));
        $allowed = [
            UluleProjectCatalog::STATUS_PENDING,
            UluleProjectCatalog::STATUS_SKIPPED,
        ];
        if (!in_array($status, $allowed, true)) {
            $this->addFlash('danger', 'Statut demandé invalide.');
            return $this->redirectToSafeReferer($request, 'admin_ulule_catalog');
        }

        $entry = $catalogRepository->findOneByUluleId($ululeId);
        if (!$entry) {
            $this->addFlash('danger', 'Projet Ulule introuvable.');
            return $this->redirectToSafeReferer($request, 'admin_ulule_catalog');
        }

        $entry->setImportStatus($status);
        if ($status === UluleProjectCatalog::STATUS_SKIPPED) {
            $entry->setImportStatusComment('manual_skip');
        } else {
            $entry->setImportStatusComment(null);
        }

        $em->flush();
        $this->addFlash('success', 'Statut mis à jour.');

        return $this->redirectToSafeReferer($request, 'admin_ulule_catalog');
    }

    private function refreshCatalog(
        UluleApiClient $ululeApiClient,
        UluleProjectCatalogRepository $catalogRepository,
        EntityManagerInterface $em,
        string $lang,
        string $country,
        string $status,
        string $sort,
        int $pageStart,
        int $pageCount,
        string $extraQuery
    ): array {
        $summary = [
            'total' => 0,
            'updated' => 0,
            'errors' => 0,
        ];
        $queryString = $this->buildQueryString($lang, $country, $status, $sort, $extraQuery);
        $now = new \DateTimeImmutable();

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
                $this->addFlash('danger', sprintf(
                    'Erreur search Ulule (page %d): %s',
                    $pageStart + $page,
                    $e->getMessage()
                ));
                continue;
            }

            $projects = $search['projects'] ?? [];
            foreach ($projects as $project) {
                $summary['total']++;
                if (!$this->isProjectType($project)) {
                    continue;
                }

                $ululeId = (int) ($project['id'] ?? 0);
                if ($ululeId <= 0) {
                    continue;
                }

                $entry = $catalogRepository->findOneByUluleId($ululeId);
                if (!$entry) {
                    $entry = new UluleProjectCatalog($ululeId);
                }

                $entry->setName($this->extractI18nString($project['name'] ?? null, $lang, null));
                $entry->setSubtitle($this->extractI18nString($project['subtitle'] ?? null, $lang, null));
                $entry->setSlug(is_string($project['slug'] ?? null) ? $project['slug'] : null);
                $entry->setSourceUrl(is_string($project['absolute_url'] ?? null) ? $project['absolute_url'] : null);
                $entry->setLang(is_string($project['lang'] ?? null) ? $project['lang'] : $lang);
                $entry->setCountry(is_string($project['country'] ?? null) ? $project['country'] : $country);
                $entry->setType($this->normalizeType($project['type'] ?? null));
                $entry->setLastSeenAt($now);

                try {
                    $detail = $ululeApiClient->getProject($ululeId, ['lang' => $lang]);
                    $fallbackLang = is_string($detail['lang'] ?? null) ? $detail['lang'] : null;
                    $description = $this->extractDescription($detail, $lang, $fallbackLang);
                    $entry->setDescriptionLength($this->plainTextLength($description));
                    $entry->setGoalRaised(isset($detail['goal_raised']) ? (bool) $detail['goal_raised'] : null);
                    $entry->setIsOnline(isset($detail['is_online']) ? (bool) $detail['is_online'] : null);
                    $entry->setIsCancelled(isset($detail['is_cancelled']) ? (bool) $detail['is_cancelled'] : null);
                    $entry->setSourceUrl($this->extractSourceUrl($detail, $project));
                    $entry->setLang(is_string($detail['lang'] ?? null) ? $detail['lang'] : $entry->getLang());
                    $entry->setCountry(is_string($detail['country'] ?? null) ? $detail['country'] : $entry->getCountry());
                    $entry->setType($this->normalizeType($detail['type'] ?? $entry->getType()));
                    $entry->setUluleCreatedAt($this->extractUluleCreatedAt($detail));
                    $entry->setLastError(null);
                } catch (\Throwable $e) {
                    $entry->setLastError($e->getMessage());
                    $summary['errors']++;
                }

                $presentation = null;
                if ($entry->getSourceUrl()) {
                    $presentation = $this->findPresentationBySourceUrl($em, $entry->getSourceUrl());
                }
                if ($presentation) {
                    $entry->setImportStatus(UluleProjectCatalog::STATUS_IMPORTED);
                    $entry->setImportedStringId($presentation->getStringId());
                } elseif ($entry->getImportStatus() === null) {
                    $entry->setImportStatus(UluleProjectCatalog::STATUS_PENDING);
                }

                $em->persist($entry);
                $summary['updated']++;
            }
        }

        $em->flush();

        return $summary;
    }

    /**
     * @return UluleProjectCatalog[]
     */
    private function loadItems(UluleProjectCatalogRepository $repository, string $statusFilter): array
    {
        $qb = $repository->createQueryBuilder('u')
            ->orderBy('u.ululeCreatedAt', 'DESC')
            ->addOrderBy('u.ululeId', 'DESC')
            ->addOrderBy('u.lastSeenAt', 'DESC');

        if ($statusFilter !== 'all') {
            $qb->andWhere('u.importStatus = :status')
                ->setParameter('status', $statusFilter);
        }

        return $qb->getQuery()->getResult();
    }

    private function isEligible(UluleProjectCatalog $item, int $minDescriptionLength, bool $excludeFunded): bool
    {
        if ($item->getImportStatus() === UluleProjectCatalog::STATUS_IMPORTED) {
            return false;
        }
        if ($excludeFunded && $item->getGoalRaised()) {
            return false;
        }
        if ($item->getIsCancelled()) {
            return false;
        }
        if ($item->getIsOnline() === false) {
            return false;
        }
        $length = $item->getDescriptionLength();
        if ($length === null) {
            return false;
        }
        if ($minDescriptionLength > 0 && $length < $minDescriptionLength) {
            return false;
        }

        return true;
    }

    private function formatRelativeDate(?\DateTimeImmutable $date, \DateTimeImmutable $now): ?string
    {
        if ($date === null) {
            return null;
        }

        $diffSeconds = $now->getTimestamp() - $date->getTimestamp();
        if ($diffSeconds <= 0) {
            return "Aujourd'hui";
        }

        $days = (int) floor($diffSeconds / 86400);
        if ($days === 0) {
            return "Aujourd'hui";
        }
        if ($days === 1) {
            return 'Hier';
        }
        if ($days < 7) {
            return sprintf('Il y a %d jours', $days);
        }
        if ($days < 30) {
            $weeks = (int) floor($days / 7);
            return sprintf('Il y a %d semaine%s', $weeks, $weeks > 1 ? 's' : '');
        }
        if ($days < 365) {
            $months = (int) floor($days / 30);
            return sprintf('Il y a %d mois', $months);
        }

        $years = (int) floor($days / 365);
        return sprintf('Il y a %d an%s', $years, $years > 1 ? 's' : '');
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
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
        return $this->parseDate($detail['date_creation'] ?? null)
            ?? $this->parseDate($detail['date_online'] ?? null)
            ?? $this->parseDate($detail['date_start'] ?? null);
    }

    private function normalizeType(mixed $type): ?string
    {
        if (is_string($type)) {
            return $type;
        }
        if (is_int($type)) {
            return (string) $type;
        }
        return null;
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

    private function extractI18nString(mixed $value, string $lang, ?string $fallbackLang): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $val = trim($value);
            return $val === '' ? null : $val;
        }
        if (!is_array($value)) {
            return null;
        }
        if (isset($value[$lang]) && is_string($value[$lang])) {
            $val = trim($value[$lang]);
            return $val === '' ? null : $val;
        }
        if ($fallbackLang && isset($value[$fallbackLang]) && is_string($value[$fallbackLang])) {
            $val = trim($value[$fallbackLang]);
            return $val === '' ? null : $val;
        }
        foreach ($value as $val) {
            if (is_string($val)) {
                $trimmed = trim($val);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
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
}
