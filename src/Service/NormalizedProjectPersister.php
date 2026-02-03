<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\PPBase;
use App\Entity\Place;
use App\Entity\Slide;
use App\Entity\User;
use App\Entity\Embeddables\GeoPoint;
use App\Enum\SlideType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Handler\UploadHandler;

class NormalizedProjectPersister
{
    private const STANDARD_FALLBACK_BASE_DIR = 'public/media/static/images/larger/project_presentation_std_images_fallbacks';
    private const STANDARD_FALLBACK_FOLDERS = [
        'administrative',
        'health',
        'education_or_inform',
        'legal_advice',
        'loneliness',
        'home_care',
        'homeless',
        'physical_activity',
        'athletism',
        'stadium',
        'community_support',
        'coaching_guidance',
        'citizenship',
        'craft_activities',
        'general_animals',
        'cats',
        'dogs',
        'nature',
        'sea',
        'talk_and_support',
        'social_inclusion',
        'volunteer_distribution',
        'fallback',
    ];
    private const STANDARD_FALLBACK_LICENCE = "Image d’illustration non affiliée";
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $lastPlaceDebug = [];
    /**
     * @var array<string, mixed>
     */
    private array $lastMediaDebug = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ImageDownloader $downloader,
        private readonly CacheThumbnailService $thumbnailService,
        private readonly WebsiteProcessingService $websiteProcessor,
        private readonly PlaceNormalizationService $placeNormalizationService,
        private readonly UploadHandler $uploadHandler,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Persist a normalized project payload into PPBase.
     *
     * @param array<string,mixed> $payload
     */
    public function persist(array $payload, User $creator): PPBase
    {
        $this->lastPlaceDebug = [];
        $this->lastMediaDebug = [];

        $pp = new PPBase();
        $pp->setCreator($creator);
        $pp->setTitle($payload['title'] ?? null);
        $pp->setGoal($payload['goal'] ?? '');
        $pp->setTextDescription($payload['description_html'] ?? null);
        $pp->setOriginLanguage('fr');
        $fundingPayload = $payload['funding'] ?? null;
        if (!is_array($fundingPayload)) {
            $fundingPayload = [];
        }
        $fundingEndAt = $payload['funding_end_at'] ?? $payload['funding_end_date'] ?? $fundingPayload['end_at'] ?? $fundingPayload['end_date'] ?? null;
        $fundingStatus = $payload['funding_status'] ?? $fundingPayload['status'] ?? null;
        $fundingPlatform = $payload['funding_platform'] ?? $fundingPayload['platform'] ?? null;
        $pp->setFundingEndAt($this->parseFundingEndAt($fundingEndAt));
        $pp->setFundingStatus($this->normalizeFundingStatus($fundingStatus));
        $pp->setFundingPlatform($this->stringValue($fundingPlatform));

        $mediaBaseName = $this->buildMediaBaseName($payload);

        // Ingestion metadata
        $ing = $pp->getIngestion();
        $sourceUrl = $this->stringValue($payload['source_url'] ?? null);
        $ing->setSourceUrl($sourceUrl);
        $ing->setSourceCreatedAt($this->parseSourceDateTime($payload['source_created_at'] ?? $payload['created_at'] ?? null));
        $ing->setSourceUpdatedAt($this->parseSourceDateTime($payload['source_updated_at'] ?? $payload['updated_at'] ?? null));
        $ing->setIngestedAt(new \DateTimeImmutable());
        $ing->setIngestionStatus('ok');

        $this->em->persist($pp);

        // Categories
        $this->attachCategories($pp, $payload['categories'] ?? []);

        // Images as slides (with optional captions)
        $logoUrl = (!empty($payload['logo_url']) && is_string($payload['logo_url'])) ? $payload['logo_url'] : null;
        $thumbnailUrl = (!empty($payload['thumbnail_url']) && is_string($payload['thumbnail_url'])) ? $payload['thumbnail_url'] : null;
        $keepThumbnailInSlides = !empty($payload['keep_thumbnail_in_slides']);
        $imagesPayload = [];
        if (!empty($payload['images']) && is_array($payload['images'])) {
            $imagesPayload = $payload['images'];
        } elseif (!empty($payload['image_urls']) && is_array($payload['image_urls'])) {
            $imagesPayload = $payload['image_urls'];
        }
        // If no logo is set but an image caption mentions a logo, promote it to logo_url and drop from slides.
        if ($logoUrl === null && is_array($imagesPayload)) {
            $promotedLogo = $this->extractLogoFromImages($imagesPayload);
            if ($promotedLogo) {
                $logoUrl = $promotedLogo;
            }
        }
        // Logo
        if ($logoUrl) {
            $this->attachLogo($pp, $logoUrl, $mediaBaseName, $sourceUrl);
        }

        // Dedicated thumbnail (custom thumbnail takes precedence in cache)
        if ($thumbnailUrl) {
            $this->attachCustomThumbnail($pp, $thumbnailUrl, $mediaBaseName, $sourceUrl);
            if (!$keepThumbnailInSlides) {
                $imagesPayload = $this->filterImagesPayload($imagesPayload, $thumbnailUrl);
            }
        }
        
        $this->attachVideos($pp, $payload['videos'] ?? []);
        $this->attachImages($pp, $imagesPayload, $logoUrl, $mediaBaseName, $sourceUrl);
        $this->attachWebsites($pp, $payload['websites'] ?? [], $sourceUrl);
        $this->attachBusinessCards($pp, $payload['business_cards'] ?? [], $sourceUrl);
        $this->applyStandardFallbackImage($pp, $payload, $mediaBaseName);
        
        $placesDefaultCountry = $this->stringValue($payload['places_default_country'] ?? null);
        $this->attachPlaces($pp, $payload['places'] ?? [], $placesDefaultCountry);

        // Q&A as other components
        $this->attachQuestions($pp, $payload['qa'] ?? []);

        // Keywords
        $this->attachKeywords($pp, $payload['keywords'] ?? []);

        // Slug/stringId if title present
        if ($pp->getTitle()) {
            $pp->setStringId($this->buildUniqueStringId($pp->getTitle()));
        }

        $this->em->flush();

        // Generate/update thumbnail (uses slide, custom thumb, or logo fallback)
        $this->thumbnailService->updateThumbnail($pp, true);

        return $pp;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLastPlaceDebug(): array
    {
        return $this->lastPlaceDebug;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastMediaDebug(): array
    {
        return $this->lastMediaDebug;
    }

    private function attachCategories(PPBase $pp, array $cats): void
    {
        $cats = array_slice($cats, 0, 3);
        foreach ($cats as $cat) {
            if (!is_string($cat) || $cat === '') {
                continue;
            }
            $category = $this->categoryRepository->findOneBy(['uniqueName' => $cat]);
            if ($category instanceof Category) {
                $pp->addCategory($category);
            }
        }
    }

    private function attachVideos(PPBase $pp, array $videos): void
    {
        $maxVideos = 3;
        $count = 0;
        foreach ($videos as $entry) {
            $url = null;
            $caption = null;

            if (is_array($entry)) {
                $url = $entry['url'] ?? null;
                $caption = $entry['caption'] ?? null;
            } else {
                $url = $entry;
            }

            if (!is_string($url) || $url === '') {
                continue;
            }

            if ($pp->getSlides()->count() >= 8) {
                break;
            }

            $slide = new Slide();
            $slide->setType(SlideType::YOUTUBE_VIDEO);
            $slide->setYoutubeUrl($url);
            $slide->setPosition($pp->getSlides()->count());
            if (is_string($caption)) {
                $caption = trim($caption);
                $slide->setCaption($caption === '' ? null : $caption);
            }
            $slide->setProjectPresentation($pp);
            $pp->addSlide($slide);

            $count++;
            if ($count >= $maxVideos) {
                break;
            }
        }
    }

    private function attachImages(
        PPBase $pp,
        array $images,
        ?string $logoUrl = null,
        ?string $mediaBaseName = null,
        ?string $referer = null
    ): void
    {
        $maxSlides = 3;
        $seen = [];
        $slideIndex = 1;
        foreach ($images as $entry) {
            $url = null;
            $caption = null;
            $licence = null;

            if (is_array($entry)) {
                $url = $entry['url'] ?? null;
                $caption = $entry['caption'] ?? null;
                $licence = $entry['licence'] ?? $entry['license'] ?? null;
            } else {
                $url = $entry;
            }

            if (!is_string($url) || $url === '') {
                continue;
            }
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            // Skip logo to avoid creating a slide for it
            if ($logoUrl && $url === $logoUrl) {
                continue;
            }

            if ($pp->getSlides()->count() >= 8) {
                break;
            }
            $preferredName = $mediaBaseName ? sprintf('image-slide-%s-%d', $mediaBaseName, $slideIndex) : null;
            $file = $this->downloader->download($url, $preferredName, $referer);
            if (!$file) {
                continue;
            }

            $slide = new Slide();
            $slide->setType(SlideType::IMAGE);
            $slide->setPosition($pp->getSlides()->count());
            $slide->setImageFile($file);
            if (is_string($caption)) {
                $caption = trim($caption);
                $slide->setCaption($caption === '' ? null : $caption);
            }
            $licence = $this->stringValue($licence);
            if ($licence !== null) {
                $slide->setLicence($licence);
            }
            $slide->setProjectPresentation($pp);
            $pp->addSlide($slide);

            $slideIndex++;
            if (count($seen) >= $maxSlides) {
                break;
            }
        }
    }

    private function attachPlaces(PPBase $pp, mixed $places, ?string $defaultCountry = null): void
    {
        if (!is_array($places) || $places === []) {
            return;
        }

        $result = $this->placeNormalizationService->normalizePlacesWithDebug($places, 3, $defaultCountry);
        $normalizedPlaces = $result['places'];
        $this->lastPlaceDebug = $result['debug'];

        if ($normalizedPlaces === []) {
            return;
        }

        $seen = [];
        foreach ($pp->getPlaces() as $existing) {
            if (!$existing instanceof Place) {
                continue;
            }
            $seen[$this->placeFingerprintFromEntity($existing)] = true;
        }

        $position = $pp->getPlaces()->count();
        foreach ($normalizedPlaces as $data) {
            $fingerprint = $this->placeFingerprintFromData($data);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;
            if (!is_float($lat) && !is_int($lat)) {
                continue;
            }
            if (!is_float($lng) && !is_int($lng)) {
                continue;
            }

            $place = (new Place())
                ->setType((string) ($data['type'] ?? 'generic'))
                ->setName($data['name'] ?? null)
                ->setCountry($data['country'] ?? null)
                ->setAdministrativeAreaLevel1($data['administrative_area_level_1'] ?? null)
                ->setAdministrativeAreaLevel2($data['administrative_area_level_2'] ?? null)
                ->setLocality($data['locality'] ?? null)
                ->setSublocalityLevel1($data['sublocality_level_1'] ?? null)
                ->setPostalCode($data['postal_code'] ?? null)
                ->setGeoloc(new GeoPoint((float) $lat, (float) $lng))
                ->setPosition($position++);

            $pp->addPlace($place);
            $this->em->persist($place);

            $seen[$fingerprint] = true;
        }
    }

    private function placeFingerprintFromEntity(Place $place): string
    {
        $geo = $place->getGeoloc();
        $lat = $geo->getLatitude();
        $lng = $geo->getLongitude();
        if ($lat !== null && $lng !== null) {
            return sprintf('geo:%s,%s', round($lat, 6), round($lng, 6));
        }

        $name = strtolower(trim((string) $place->getName()));
        $locality = strtolower(trim((string) $place->getLocality()));
        $country = strtolower(trim((string) $place->getCountry()));

        return sprintf('text:%s|%s|%s', $name, $locality, $country);
    }

    private function placeFingerprintFromData(array $data): string
    {
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if (is_float($lat) || is_int($lat)) {
            $lat = round((float) $lat, 6);
        }
        if (is_float($lng) || is_int($lng)) {
            $lng = round((float) $lng, 6);
        }
        if ($lat !== null && $lng !== null) {
            return sprintf('geo:%s,%s', $lat, $lng);
        }

        $name = strtolower(trim((string) ($data['name'] ?? '')));
        $locality = strtolower(trim((string) ($data['locality'] ?? '')));
        $country = strtolower(trim((string) ($data['country'] ?? '')));

        return sprintf('text:%s|%s|%s', $name, $locality, $country);
    }

    /**
     * Promote an image to logo when its caption mentions a logo.
     *
     * @param array<int, mixed> $images
     */
    private function extractLogoFromImages(array &$images): ?string
    {
        foreach ($images as $idx => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $url = $entry['url'] ?? null;
            $caption = $entry['caption'] ?? null;
            if (!is_string($url) || $url === '' || !is_string($caption)) {
                continue;
            }
            if (stripos($caption, 'logo') !== false) {
                unset($images[$idx]); // remove from images so it won't become a slide
                return $url;
            }
        }

        return null;
    }

    private function attachLogo(PPBase $pp, string $url, string $mediaBaseName, ?string $referer = null): void
    {
        $file = $this->downloader->download(
            $url,
            sprintf('logo-used-for-project-%s', $mediaBaseName),
            $referer
        );
        if ($file) {
            $pp->setLogoFile($file);
            $logoDebug = [
                'downloaded' => true,
                'client_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
            // Force-upload in ingestion pipelines when Vich doesn't set the logo on flush (observed in URL harvest).
            if ($file instanceof UploadedFile && $pp->getLogo() === null) {
                try {
                    $this->uploadHandler->upload($pp, 'logoFile');
                    $pp->setLogoFile(null);
                    $logoDebug['stored'] = $pp->getLogo();
                } catch (\Throwable $e) {
                    $logoDebug['upload_error'] = $e->getMessage();
                }
            } elseif ($pp->getLogo() !== null) {
                $logoDebug['upload_skipped'] = 'already_set';
            }
            $this->lastMediaDebug['logo'] = $logoDebug;
        } else {
            $this->lastMediaDebug['logo'] = [
                'downloaded' => false,
            ];
        }
    }

    private function attachCustomThumbnail(PPBase $pp, string $url, string $mediaBaseName, ?string $referer = null): void
    {
        $file = $this->downloader->download(
            $url,
            sprintf('thumb-used-for-project-%s', $mediaBaseName),
            $referer
        );
        if ($file) {
            $pp->setCustomThumbnailFile($file);
        }
    }

    private function attachQuestions(PPBase $pp, array $qa): void
    {
        $qa = array_slice($qa, 0, 4);
        $oc = $pp->getOtherComponents();
        foreach ($qa as $item) {
            if (!is_array($item)) {
                continue;
            }
            $q = $item['question'] ?? null;
            $a = $item['answer'] ?? null;
            if (!is_string($q) || !is_string($a) || $q === '' || $a === '') {
                continue;
            }
            $component = QuestionAnswerComponent::createNew($q, $a);
            $oc->addComponent('questions_answers', $component);
        }
        $pp->setOtherComponents($oc);
    }

    private function applyStandardFallbackImage(PPBase $pp, array $payload, ?string $mediaBaseName = null): void
    {
        $folder = $this->normalizeStandardFallbackFolder($payload['standard_image_fallback_name'] ?? null);
        if ($folder === null) {
            return;
        }

        if ($pp->getCustomThumbnail() || $pp->getLogo() || $pp->getSlides()->count() > 0) {
            return;
        }

        $absolutePath = $this->pickStandardFallbackImage($folder);
        if ($absolutePath === null) {
            return;
        }

        $preferredName = $mediaBaseName ? sprintf('fallback-slide-%s', $mediaBaseName) : 'fallback-slide';
        $file = $this->createUploadedFileFromLocalImage($absolutePath, $preferredName);
        if ($file === null) {
            return;
        }

        $slide = new Slide();
        $slide->setType(SlideType::IMAGE);
        $slide->setPosition($pp->getSlides()->count());
        $slide->setImageFile($file);
        $slide->setLicence(self::STANDARD_FALLBACK_LICENCE);
        $slide->setProjectPresentation($pp);
        $pp->addSlide($slide);

        $this->lastMediaDebug['standard_fallback'] = [
            'folder' => $folder,
            'selected' => basename($absolutePath),
        ];
    }

    private function normalizeStandardFallbackFolder(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $folder = strtolower(trim($value));
        if ($folder === '') {
            return null;
        }

        if (!in_array($folder, self::STANDARD_FALLBACK_FOLDERS, true)) {
            $folder = 'fallback';
        }

        return $folder;
    }

    private function pickStandardFallbackImage(string $folder): ?string
    {
        $base = rtrim($this->projectDir, '/') . '/' . self::STANDARD_FALLBACK_BASE_DIR . '/' . $folder;
        $files = is_dir($base) ? glob($base . '/*.{avif,webp,png,jpg,jpeg}', GLOB_BRACE) : [];
        if (is_array($files) && $files !== []) {
            return $files[array_rand($files)];
        }

        if ($folder === 'fallback') {
            return null;
        }

        $fallbackBase = rtrim($this->projectDir, '/') . '/' . self::STANDARD_FALLBACK_BASE_DIR . '/fallback';
        $fallbackFiles = is_dir($fallbackBase) ? glob($fallbackBase . '/*.{avif,webp,png,jpg,jpeg}', GLOB_BRACE) : [];
        if (!is_array($fallbackFiles) || $fallbackFiles === []) {
            return null;
        }

        return $fallbackFiles[array_rand($fallbackFiles)];
    }

    private function createUploadedFileFromLocalImage(string $absolutePath, string $baseName): ?UploadedFile
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'fallback_');
        if ($tmpPath === false) {
            return null;
        }

        if (!copy($absolutePath, $tmpPath)) {
            return null;
        }

        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName) ?? 'fallback';
        $safeBase = trim($safeBase, '-');
        if ($safeBase === '') {
            $safeBase = 'fallback';
        }

        $mimeType = mime_content_type($absolutePath) ?: null;

        return new UploadedFile(
            $tmpPath,
            $safeBase . $extension,
            $mimeType ?: null,
            null,
            true
        );
    }

    private function attachKeywords(PPBase $pp, array $keywords): void
    {
        $keywords = array_slice(array_filter($keywords, fn($k) => is_string($k) && $k !== ''), 0, 5);
        if (!$keywords) {
            return;
        }
        // store as comma-separated string
        $pp->setKeywords(implode(', ', $keywords));
    }

    private function attachWebsites(PPBase $pp, array $websites, ?string $sourceUrl = null): void
    {
        $oc = $pp->getOtherComponents();
        $seen = [];

        // For JeVeuxAider imports, always keep the source page as a website entry.
        if ($this->isJeVeuxAiderSource($sourceUrl) && is_string($sourceUrl) && $sourceUrl !== '') {
            $websites[] = [
                'title' => 'Page JeVeuxAider.gouv',
                'url' => $sourceUrl,
            ];
        }

        foreach ($websites as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $url = $entry['url'] ?? null;
            $title = $entry['title'] ?? null;
            if (!is_string($url) || $url === '' || !is_string($title) || $title === '') {
                continue;
            }

            $canonicalUrl = $this->canonicalizeWebsiteUrl($url);
            if (isset($seen[$canonicalUrl])) {
                continue;
            }
            $seen[$canonicalUrl] = true;

            $component = WebsiteComponent::createNew($title, $url);
            try {
                $component = $this->websiteProcessor->process($component);
                $oc->addComponent('websites', $component);
            } catch (\Throwable) {
                continue;
            }
        }
        $pp->setOtherComponents($oc);
    }

    private function canonicalizeWebsiteUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return strtolower(rtrim(trim($url), '/'));
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www\.|m\.)/i', '', $host);
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $host . $path . $query;
    }

    private function attachBusinessCards(PPBase $pp, array $cards, ?string $sourceUrl = null): void
    {
        $cards = array_slice($cards, 0, 2);
        if ($cards === []) {
            return;
        }

        $isJeVeuxAider = $this->isJeVeuxAiderSource($sourceUrl);
        $oc = $pp->getOtherComponents();
        foreach ($cards as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $title = $this->stringValue($entry['title'] ?? null);
            $email1 = $this->stringValue($entry['email1'] ?? null);
            $tel1 = $this->stringValue($entry['tel1'] ?? null);
            $website1 = $this->stringValue($entry['website1'] ?? null);
            $website2 = $this->stringValue($entry['website2'] ?? null);
            $postalMail = $this->stringValue($entry['postalMail'] ?? null);
            if ($isJeVeuxAider) {
                $postalMail = $this->normalizeJeVeuxAiderPostalMail($postalMail);
            }
            $remarks = $this->stringValue($entry['remarks'] ?? null);

            if ($title === null && $email1 === null && $tel1 === null && $website1 === null && $website2 === null && $postalMail === null && $remarks === null) {
                continue;
            }

            $component = BusinessCardComponent::createNew();
            $component->setTitle($title);
            $component->setEmail1($email1);
            $component->setTel1($tel1);
            $component->setWebsite1($website1);
            $component->setWebsite2($website2);
            $component->setPostalMail($postalMail);
            $component->setRemarks($remarks);

            $oc->addComponent('business_cards', $component);
        }

        $pp->setOtherComponents($oc);
    }

    /**
     * JeVeuxAider pages often repeat the city line before the "ZIP City" line.
     */
    private function normalizeJeVeuxAiderPostalMail(?string $postalMail): ?string
    {
        if ($postalMail === null) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($postalMail));
        if (!is_array($lines)) {
            return $postalMail;
        }

        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line) => $line !== ''));
        if ($lines === []) {
            return null;
        }

        $normalized = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $this->normalizeJeVeuxAiderPostalLine($lines[$i]);
            $next = $lines[$i + 1] ?? null;
            $nextPostal = $next !== null ? $this->parsePostalLine($next) : null;
            if ($nextPostal !== null
                && !$this->lineHasDigits($line)
                && $this->normalizeCity($line) === $this->normalizeCity($nextPostal['city'])) {
                continue;
            }

            $postal = $this->parsePostalLine($line);
            if ($postal !== null && $normalized !== []) {
                $prev = $normalized[count($normalized) - 1];
                if (!$this->lineHasDigits($prev)
                    && $this->normalizeCity($prev) === $this->normalizeCity($postal['city'])) {
                    array_pop($normalized);
                }
            }

            $normalized[] = $line;
        }

        if ($normalized === []) {
            return null;
        }

        return implode("\n", $normalized);
    }

    /**
     * @return array{zip:string,city:string}|null
     */
    private function parsePostalLine(string $line): ?array
    {
        if (!preg_match('/^(\d{5})\s+(.+)$/u', $line, $matches)) {
            return null;
        }

        return [
            'zip' => $matches[1],
            'city' => $matches[2],
        ];
    }

    private function lineHasDigits(string $line): bool
    {
        return preg_match('/\d/', $line) === 1;
    }

    private function normalizeJeVeuxAiderPostalLine(string $line): string
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return $line;
        }

        $match = null;
        if (preg_match('/^(.+?)\s*,\s*(\d{5}\s+.+)$/u', $trimmed, $match) !== 1) {
            if (preg_match('/^(.+?)\s+(\d{5}\s+.+)$/u', $trimmed, $match) !== 1) {
                return $line;
            }
        }

        $prefix = trim($match[1]);
        $postalPart = trim($match[2]);

        if ($prefix === '' || $this->lineHasDigits($prefix)) {
            return $line;
        }

        $postal = $this->parsePostalLine($postalPart);
        if ($postal === null) {
            return $line;
        }

        if ($this->normalizeCity($prefix) === $this->normalizeCity($postal['city'])) {
            return $postalPart;
        }

        return $line;
    }

    private function normalizeCity(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    private function isJeVeuxAiderSource(?string $sourceUrl): bool
    {
        if ($sourceUrl === null || $sourceUrl === '') {
            return false;
        }

        return str_contains($sourceUrl, 'jeveuxaider.gouv.fr');
    }

    /**
     * @param array<int, mixed> $images
     *
     * @return array<int, mixed>
     */
    private function filterImagesPayload(array $images, string $skipUrl): array
    {
        $filtered = [];
        foreach ($images as $entry) {
            if (is_array($entry)) {
                $url = $entry['url'] ?? null;
                if ($url === $skipUrl) {
                    continue;
                }
            } elseif ($entry === $skipUrl) {
                continue;
            }
            $filtered[] = $entry;
        }

        return $filtered;
    }

    private function parseFundingEndAt(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable())->setTimestamp($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSourceDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }


    private function normalizeFundingStatus(mixed $value): ?string
    {
        $status = $this->stringValue($value);
        if ($status === null) {
            return null;
        }

        $status = strtolower($status);

        return match ($status) {
            'success', 'succeeded', 'funded', 'goal_raised', 'reached', 'completed_success' => 'success',
            'failed', 'failure', 'unsuccessful', 'not_funded', 'unfunded' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'ongoing', 'in_progress', 'active', 'online', 'live', 'funding' => 'ongoing',
            'ended', 'finished', 'closed' => 'ended',
            default => $status,
        };
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
     * @param array<string, mixed> $payload
     */
    private function buildMediaBaseName(array $payload): string
    {
        $raw = $payload['title'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $payload['goal'] ?? null;
        }
        if (!is_string($raw) || trim($raw) === '') {
            $raw = 'projet';
        }

        $slugger = new AsciiSlugger();
        $slug = strtolower((string) $slugger->slug($raw));
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'projet';
        }

        if (strlen($slug) > 60) {
            $slug = rtrim(substr($slug, 0, 60), '-');
        }

        return $slug === '' ? 'projet' : $slug;
    }

    private function buildUniqueStringId(string $title): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($title));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'projet';
        }
        if (strlen($base) > 190) {
            $base = rtrim(substr($base, 0, 190), '-');
            if ($base === '') {
                $base = 'projet';
            }
        }

        /** @var \Doctrine\ORM\EntityRepository<PPBase> $repository */
        $repository = $this->em->getRepository(PPBase::class);
        $candidate = $base;
        $counter = 1;

        while ($repository->findOneBy(['stringId' => $candidate]) !== null) {
            $suffix = '-' . $counter++;
            $maxBaseLength = 190 - strlen($suffix);
            $trimmedBase = rtrim(substr($base, 0, max(1, $maxBaseLength)), '-');
            if ($trimmedBase === '') {
                $trimmedBase = 'projet';
            }
            $candidate = $trimmedBase . $suffix;
        }

        return $candidate;
    }
}
