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
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class NormalizedProjectPersister
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $lastPlaceDebug = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ImageDownloader $downloader,
        private readonly CacheThumbnailService $thumbnailService,
        private readonly WebsiteProcessingService $websiteProcessor,
        private readonly PlaceNormalizationService $placeNormalizationService,
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

        $pp = new PPBase();
        $pp->setCreator($creator);
        $pp->setTitle($payload['title'] ?? null);
        $pp->setGoal($payload['goal'] ?? '');
        $pp->setTextDescription($payload['description_html'] ?? null);
        $pp->setOriginLanguage('fr');

        // Ingestion metadata
        $ing = $pp->getIngestion();
        $ing->setSourceUrl($payload['source_url'] ?? null);
        $ing->setIngestedAt(new \DateTimeImmutable());
        $ing->setIngestionStatus('ok');

        // Categories
        $this->attachCategories($pp, $payload['categories'] ?? []);

        // Images as slides (with optional captions)
        $logoUrl = (!empty($payload['logo_url']) && is_string($payload['logo_url'])) ? $payload['logo_url'] : null;
        $thumbnailUrl = (!empty($payload['thumbnail_url']) && is_string($payload['thumbnail_url'])) ? $payload['thumbnail_url'] : null;
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
            $this->attachLogo($pp, $logoUrl);
        }

        // Dedicated thumbnail (custom thumbnail takes precedence in cache)
        if ($thumbnailUrl) {
            $this->attachCustomThumbnail($pp, $thumbnailUrl);
            $imagesPayload = $this->filterImagesPayload($imagesPayload, $thumbnailUrl);
        }
        
        $this->attachVideos($pp, $payload['videos'] ?? []);
        $this->attachImages($pp, $imagesPayload, $logoUrl);
        $this->attachWebsites($pp, $payload['websites'] ?? []);
        
        $this->attachPlaces($pp, $payload['places'] ?? []);

        // Q&A as other components
        $this->attachQuestions($pp, $payload['qa'] ?? []);

        // Keywords
        $this->attachKeywords($pp, $payload['keywords'] ?? []);

        // Slug/stringId if title present
        if ($pp->getTitle()) {
            $slugger = new AsciiSlugger();
            $pp->setStringId(strtolower($slugger->slug($pp->getTitle())));
        }

        $this->em->persist($pp);
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

    private function attachImages(PPBase $pp, array $images, ?string $logoUrl = null): void
    {
        $maxSlides = 3;
        $seen = [];
        foreach ($images as $entry) {
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
            $file = $this->downloader->download($url);
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
            $slide->setProjectPresentation($pp);
            $pp->addSlide($slide);

            if (count($seen) >= $maxSlides) {
                break;
            }
        }
    }

    private function attachPlaces(PPBase $pp, mixed $places): void
    {
        if (!is_array($places) || $places === []) {
            return;
        }

        $result = $this->placeNormalizationService->normalizePlacesWithDebug($places, 3);
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

    private function attachLogo(PPBase $pp, string $url): void
    {
        $file = $this->downloader->download($url);
        if ($file) {
            $pp->setLogoFile($file);
        }
    }

    private function attachCustomThumbnail(PPBase $pp, string $url): void
    {
        $file = $this->downloader->download($url);
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

    private function attachKeywords(PPBase $pp, array $keywords): void
    {
        $keywords = array_slice(array_filter($keywords, fn($k) => is_string($k) && $k !== ''), 0, 5);
        if (!$keywords) {
            return;
        }
        // store as comma-separated string
        $pp->setKeywords(implode(', ', $keywords));
    }

    private function attachWebsites(PPBase $pp, array $websites): void
    {
        $oc = $pp->getOtherComponents();
        foreach ($websites as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $url = $entry['url'] ?? null;
            $title = $entry['title'] ?? null;
            if (!is_string($url) || $url === '' || !is_string($title) || $title === '') {
                continue;
            }
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

}
