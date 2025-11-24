<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\PPBase;
use App\Entity\Slide;
use App\Enum\SlideType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class NormalizedProjectPersister
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ImageDownloader $downloader,
    ) {
    }

    /**
     * Persist a normalized project payload into PPBase.
     *
     * @param array<string,mixed> $payload
     */
    public function persist(array $payload, int $creatorId): PPBase
    {
        $pp = new PPBase();
        $pp->setCreator($this->em->getRepository(\App\Entity\User::class)->find($creatorId));
        $pp->setTitle($payload['title'] ?? null);
        $pp->setGoal($payload['goal'] ?? '');
        $pp->setTextDescription($payload['description_html'] ?? null);
        $pp->setOriginLanguage('fr');

        // Logo
        if (!empty($payload['logo_url']) && is_string($payload['logo_url'])) {
            $this->attachLogo($pp, $payload['logo_url']);
        }

        // Ingestion metadata
        $ing = $pp->getIngestion();
        $ing->setSourceUrl($payload['source_url'] ?? null);
        $ing->setIngestedAt(new \DateTimeImmutable());
        $ing->setIngestionStatus('ok');

        // Categories
        $this->attachCategories($pp, $payload['categories'] ?? []);

        // Images as slides
        $this->attachImages($pp, $payload['image_urls'] ?? []);

        // Q&A as other components
        $this->attachQuestions($pp, $payload['qa'] ?? []);

        // Slug/stringId if title present
        if ($pp->getTitle()) {
            $slugger = new AsciiSlugger();
            $pp->setStringId(strtolower($slugger->slug($pp->getTitle())));
        }

        $this->em->persist($pp);
        $this->em->flush();

        return $pp;
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

    private function attachImages(PPBase $pp, array $images): void
    {
        $images = array_slice(array_unique(array_filter($images, fn($u) => is_string($u) && $u !== '')), 0, 3);
        foreach ($images as $url) {
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
            $slide->setProjectPresentation($pp);
            $pp->addSlide($slide);
        }
    }

    private function attachLogo(PPBase $pp, string $url): void
    {
        $file = $this->downloader->download($url);
        if ($file) {
            $pp->setLogoFile($file);
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
}
