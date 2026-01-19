<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\PPBase;
use App\Entity\User;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use App\Service\WebsiteProcessingService;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists normalized scraped items into PPBase without handling slides/places (MVP).
 */
class ScraperPersistenceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly WebsiteProcessingService $websiteProcessingService,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items normalized payloads
     * @return array{created:int, skipped:int, errors:array<int,string>}
     */
    public function persist(array $items, User $creator): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];
        $createdEntities = [];

        foreach ($items as $index => $item) {
            try {
                $canonicalUrl = $this->canonicalizeUrl($item['source_url'] ?? null);
                $item['source_url'] = $canonicalUrl;

                // Dedup on source_url (embedded column)
                $existing = $this->em->getRepository(PPBase::class)
                    ->createQueryBuilder('p')
                    ->where('p.ingestion.sourceUrl = :url')
                    ->setParameter('url', $canonicalUrl)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                $pp = new PPBase();
                $pp->setCreator($creator);
                $pp->setTitle($item['title'] ?? null);
                $pp->setGoal($item['goal'] ?? '');
                $pp->setTextDescription($item['description'] ?? null);
                $pp->setOriginLanguage($item['language'] ?? null);

                $this->em->persist($pp);

                // Ingestion metadata
                $ing = $pp->getIngestion();
                $ing->setSourceUrl($canonicalUrl);
                $ing->setIngestedAt(new \DateTimeImmutable());
                if (!empty($item['source_published_at']) && $item['source_published_at'] instanceof \DateTimeInterface) {
                    $ing->setSourcePublishedAt($item['source_published_at']);
                }
                $ing->setIngestionStatus($item['status'] ?? 'ok');
                $ing->setIngestionStatusComment($item['status_reason'] ?? null);

                // Categories (attach up to 3)
                $this->attachCategories($pp, $item['categories'] ?? []);

                // Websites / socials
                $this->attachWebsites($pp, $item['websites'] ?? [], $item['website'] ?? null);

                $created++;
                $createdEntities[] = $pp;
            } catch (UniqueConstraintViolationException $e) {
                $errors[] = sprintf('Item %d: doublon sur source_url (%s)', $index, $item['source_url'] ?? 'n/a');
                $this->logger->info('Scraper duplicate skipped on unique index', ['source_url' => $item['source_url'] ?? null]);
                $this->em->clear();
                $skipped++;
            } catch (\Throwable $e) {
                $errors[] = sprintf('Item %d: %s', $index, $e->getMessage());
                $this->logger->warning('Scraper persistence failed', ['exception' => $e]);
                $this->em->clear(); // avoid inconsistent state
            }
        }

        $this->em->flush();

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'entities' => $createdEntities];
    }

    private function attachCategories(PPBase $pp, array $categories): void
    {
        $categories = array_slice($categories, 0, 3);
        foreach ($categories as $cat) {
            if (!is_string($cat) || $cat === '') {
                continue;
            }
            $category = $this->categoryRepository->findOneBy(['uniqueName' => $cat]);
            if ($category instanceof Category) {
                $pp->addCategory($category);
            }
        }
    }

    private function attachWebsites(PPBase $pp, array $websites, ?string $primaryWebsite): void
    {
        $oc = $pp->getOtherComponents();

        $normalized = [];

        // Primary website first
        if (is_string($primaryWebsite) && $primaryWebsite !== '') {
            $normalized[] = [
                'title' => $this->humanizeWebsiteTitle($primaryWebsite, $pp->getTitle()),
                'url' => trim($primaryWebsite),
            ];
        }

        // Additional websites (dedupe by URL)
        foreach ($websites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $title = $site['title'] ?? null;
            $url = $site['url'] ?? null;
            if (!is_string($title) || !is_string($url) || $title === '' || $url === '') {
                continue;
            }
            $url = trim($url);
            if (array_filter($normalized, fn($w) => $w['url'] === $url)) {
                continue;
            }
            $normalized[] = [
                'title' => trim($title),
                'url' => $url,
            ];
            if (count($normalized) >= 5) {
                break;
            }
        }

        foreach ($normalized as $site) {
            $component = WebsiteComponent::createNew($site['title'], $site['url']);
            $this->websiteProcessingService->process($component);
            $oc->addComponent('websites', $component);
        }

        $pp->setOtherComponents($oc);
    }

    private function humanizeWebsiteTitle(string $url, ?string $fallback): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $host = strtolower($host);
            if (str_starts_with($host, 'www.')) {
                $host = substr($host, 4);
            }
            return $host;
        }

        return $fallback ?? 'Site web';
    }

    private function canonicalizeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Alias map for known host variants
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);

        // Strip common www prefix
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return sprintf('%s://%s%s%s', $scheme, $host, $path, $query);
    }

}
