<?php

namespace App\Service;

use App\Entity\PPBase;
use App\Entity\UluleProjectCatalog;
use App\Repository\UluleProjectCatalogRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UluleCatalogRefresher
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly UluleApiClient $ululeApiClient,
        private readonly UluleProjectCatalogRepository $catalogRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function refreshCatalog(
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
            'new' => 0,
            'new_ids' => [],
            'errors' => 0,
            'error_messages' => [],
        ];
        $queryString = $this->buildQueryString($lang, $country, $status, $sort, $extraQuery);
        $now = new \DateTimeImmutable();

        for ($page = 0; $page < $pageCount; $page++) {
            $offset = ($pageStart - 1 + $page) * self::PER_PAGE;
            try {
                $search = $this->ululeApiClient->searchProjects([
                    'lang' => $lang,
                    'limit' => self::PER_PAGE,
                    'offset' => $offset,
                    'q' => $queryString,
                ]);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['error_messages'][] = sprintf(
                    'Erreur search Ulule (page %d): %s',
                    $pageStart + $page,
                    $e->getMessage()
                );
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

                $entry = $this->catalogRepository->findOneByUluleId($ululeId);
                $isNew = false;
                if (!$entry) {
                    $entry = new UluleProjectCatalog($ululeId);
                    $isNew = true;
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
                    $detail = $this->ululeApiClient->getProject($ululeId, ['lang' => $lang]);
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
                    $presentation = $this->findPresentationBySourceUrl($entry->getSourceUrl());
                }
                if ($presentation) {
                    $entry->setImportStatus(UluleProjectCatalog::STATUS_IMPORTED);
                    $entry->setImportedStringId($presentation->getStringId());
                } elseif ($entry->getImportStatus() === null) {
                    $entry->setImportStatus(UluleProjectCatalog::STATUS_PENDING);
                }

                $this->em->persist($entry);
                $summary['updated']++;
                if ($isNew) {
                    $summary['new']++;
                    $summary['new_ids'][] = $ululeId;
                }
            }
        }

        $this->em->flush();

        return $summary;
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

    private function findPresentationBySourceUrl(string $sourceUrl): ?PPBase
    {
        return $this->em->getRepository(PPBase::class)
            ->createQueryBuilder('p')
            ->where('p.ingestion.sourceUrl = :url')
            ->setParameter('url', $sourceUrl)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
