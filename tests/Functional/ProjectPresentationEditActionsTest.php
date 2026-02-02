<?php

namespace App\Tests\Functional;

use App\Entity\Document;
use App\Entity\Category;
use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\Slide;
use App\Enum\SlideType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectPresentationEditActionsTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testAddImageSlideCreatesSlide(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-image-slide', $presentation->getStringId()),
            ['image_slide' => ['caption' => '', 'licence' => '', '_token' => $token]],
            ['image_slide' => ['imageFile' => ['file' => $this->createUploadedImage()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#slideshow-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertSame(SlideType::IMAGE, $slide->getType());
        self::assertNotEmpty($slide->getImagePath());
    }

    public function testAddImageSlideResizesLargeImage(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-image-slide', $presentation->getStringId()),
            ['image_slide' => ['caption' => '', 'licence' => '', '_token' => $token]],
            ['image_slide' => ['imageFile' => ['file' => $this->createUploadedLargeImage(3000, 2000)]]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertNotEmpty($slide->getImagePath());

        $slidePath = sprintf(
            '%s/public/media/uploads/pp/slides/%s',
            (string) $client->getContainer()->getParameter('kernel.project_dir'),
            $slide->getImagePath()
        );
        self::assertFileExists($slidePath);

        $imageSize = getimagesize($slidePath);
        self::assertNotFalse($imageSize);
        if ($imageSize !== false) {
            self::assertLessThanOrEqual(1920, $imageSize[0]);
            self::assertLessThanOrEqual(1080, $imageSize[1]);
        }

        if (is_file($slidePath)) {
            unlink($slidePath);
        }
    }

    public function testAddImageSlideRejectsMissingFile(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-image-slide', $presentation->getStringId()),
            ['image_slide' => ['caption' => '', 'licence' => '', '_token' => $token]]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testAddVideoSlideCreatesSlide(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-video-slide', $presentation->getStringId()),
            ['video_slide' => ['youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '_token' => $token]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#slideshow-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertSame(SlideType::YOUTUBE_VIDEO, $slide->getType());
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $slide->getYoutubeUrl());
    }

    public function testAddVideoSlideAcceptsYoutubeShortsUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-video-slide', $presentation->getStringId()),
            ['video_slide' => ['youtubeUrl' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ?feature=share', '_token' => $token]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertSame('https://www.youtube.com/shorts/dQw4w9WgXcQ?feature=share', $slide->getYoutubeUrl());
        self::assertSame('dQw4w9WgXcQ', $slide->getYoutubeVideoId());
    }

    public function testAddVideoSlideRejectsInvalidUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-video-slide', $presentation->getStringId()),
            ['video_slide' => ['youtubeUrl' => 'invalid', '_token' => $token]]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testAddDocumentCreatesDocument(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/documents', $presentation->getStringId()),
            ['document' => ['title' => 'Document de test', '_token' => $token]],
            ['document' => ['file' => ['file' => $this->createUploadedDocument()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#documents-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getDocuments());
        self::assertSame('Document de test', $presentation->getDocuments()->first()->getTitle());
    }

    public function testCreateNewsCreatesNews(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/news', $presentation->getStringId()),
            [
                'news' => [
                    'textContent' => 'Une actualité de test.',
                    'presentationId' => (string) $presentation->getId(),
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#news-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getNews());
        self::assertSame('Une actualité de test.', $presentation->getNews()->first()->getTextContent());
    }

    public function testAddWebsiteCreatesComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-website', $presentation->getStringId()),
            [
                'website' => [
                    'title' => 'Official site',
                    'url' => 'https://example.com',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $websites = $presentation->getOtherComponents()->getComponents('websites');
        self::assertCount(1, $websites);
        self::assertSame('Official site', $websites[0]->getTitle());
        self::assertSame('https://example.com', $websites[0]->getUrl());
        self::assertNotEmpty($websites[0]->getIcon());
    }

    public function testAddWebsiteRejectsInvalidUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-website', $presentation->getStringId()),
            [
                'website' => [
                    'title' => 'Official site',
                    'url' => 'invalid',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('websites'));
    }

    public function testAddQuestionAnswerCreatesComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-question-answer', $presentation->getStringId()),
            [
                'question_answer' => [
                    'question' => 'How does the project help?',
                    'answer' => 'It helps by reducing waste and sharing tools.',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $items = $presentation->getOtherComponents()->getComponents('questions_answers');
        self::assertCount(1, $items);
        self::assertSame('How does the project help?', $items[0]->getQuestion());
        self::assertSame('It helps by reducing waste and sharing tools.', $items[0]->getAnswer());
    }

    public function testAddQuestionAnswerRejectsEmptyQuestion(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-question-answer', $presentation->getStringId()),
            [
                'question_answer' => [
                    'question' => '',
                    'answer' => '',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('questions_answers'));
    }

    public function testAddBusinessCardCreatesComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-business-card', $presentation->getStringId()),
            [
                'business_card' => [
                    'title' => 'Jane Doe',
                    'email1' => 'jane@example.com',
                    'tel1' => '+33 6 12 34 56 78',
                    'website1' => 'https://example.com',
                    'website2' => 'https://linkedin.com/in/janedoe',
                    'postalMail' => '1 Rue de Test, 75000 Paris',
                    'remarks' => 'Contact by email',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $cards = $presentation->getOtherComponents()->getComponents('business_cards');
        self::assertCount(1, $cards);
        self::assertSame('Jane Doe', $cards[0]->getTitle());
        self::assertSame('jane@example.com', $cards[0]->getEmail1());
        self::assertSame('+33 6 12 34 56 78', $cards[0]->getTel1());
        self::assertSame('https://example.com', $cards[0]->getWebsite1());
    }

    public function testAddBusinessCardRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-business-card', $presentation->getStringId()),
            [
                'business_card' => [
                    'email1' => 'invalid-email',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('business_cards'));
    }

    public function testAddLogoCreatesLogo(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-logo', $presentation->getStringId()),
            ['logo' => ['_token' => $token]],
            ['logo' => ['logoFile' => ['file' => $this->createUploadedImage()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#logo-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertNotEmpty($presentation->getLogo());
        self::assertNotEmpty($presentation->getExtra()->getCacheThumbnailUrl());

        $logoPath = sprintf(
            '%s/public/media/uploads/pp/logos/%s',
            (string) $client->getContainer()->getParameter('kernel.project_dir'),
            $presentation->getLogo()
        );
        self::assertFileExists($logoPath);

        if (is_file($logoPath)) {
            unlink($logoPath);
        }
    }

    public function testAddLogoResizesLargeImage(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/add-logo', $presentation->getStringId()),
            ['logo' => ['_token' => $token]],
            ['logo' => ['logoFile' => ['file' => $this->createUploadedLargeImage(2000, 2000)]]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertNotEmpty($presentation->getLogo());

        $logoPath = sprintf(
            '%s/public/media/uploads/pp/logos/%s',
            (string) $client->getContainer()->getParameter('kernel.project_dir'),
            $presentation->getLogo()
        );
        self::assertFileExists($logoPath);

        $imageSize = getimagesize($logoPath);
        self::assertNotFalse($imageSize);
        if ($imageSize !== false) {
            self::assertLessThanOrEqual(1400, $imageSize[0]);
            self::assertLessThanOrEqual(1400, $imageSize[1]);
        }

        if (is_file($logoPath)) {
            unlink($logoPath);
        }
    }

    public function testUpdateLogoUpdatesLogo(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $presentation->setLogo('old-logo.png');
        $em->flush();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/project/%s/update/logo', $presentation->getStringId()),
            ['logo' => ['_token' => $token]],
            ['logo' => ['logoFile' => ['file' => $this->createUploadedImage()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertNotEmpty($presentation->getLogo());
        self::assertNotSame('old-logo.png', $presentation->getLogo());

        $logoPath = sprintf(
            '%s/public/media/uploads/pp/logos/%s',
            (string) $client->getContainer()->getParameter('kernel.project_dir'),
            $presentation->getLogo()
        );
        self::assertFileExists($logoPath);

        if (is_file($logoPath)) {
            unlink($logoPath);
        }
    }

    public function testEditThumbnailUpdatesCustomThumbnail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/projects/%s/edit/thumbnail', $presentation->getStringId()),
            ['thumbnail' => ['_token' => $token]],
            ['thumbnail' => ['customThumbnailFile' => ['file' => $this->createUploadedImage()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertNotEmpty($presentation->getCustomThumbnail());

        $thumbnailPath = sprintf(
            '%s/public/media/uploads/pp/custom_thumbnails/%s',
            (string) $client->getContainer()->getParameter('kernel.project_dir'),
            $presentation->getCustomThumbnail()
        );
        self::assertFileExists($thumbnailPath);

        if (is_file($thumbnailPath)) {
            unlink($thumbnailPath);
        }
    }

    public function testUpdateCategoriesKeywordsPersists(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $categoryA = (new Category())
            ->setUniqueName('energy')
            ->setLabel('Energy');
        $categoryB = (new Category())
            ->setUniqueName('climate')
            ->setLabel('Climate');

        $em->persist($categoryA);
        $em->persist($categoryB);
        $em->flush();

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/projects/%s/categories-keywords', $presentation->getStringId()),
            [
                'categories_keywords' => [
                    'categories' => ['energy', 'climate'],
                    'keywords' => 'solar, reuse',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertSame('solar, reuse', $presentation->getKeywords());

        $categoryNames = [];
        foreach ($presentation->getCategories() as $category) {
            $categoryNames[] = $category->getUniqueName();
        }

        sort($categoryNames);
        self::assertSame(['climate', 'energy'], $categoryNames);
    }

    public function testUpdateCategoriesKeywordsRejectsInvalidCategory(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $categoryA = (new Category())
            ->setUniqueName('energy')
            ->setLabel('Energy');

        $em->persist($categoryA);
        $em->flush();

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $presentation->addCategory($categoryA);
        $presentation->setKeywords('initial keywords');
        $em->flush();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/projects/%s/categories-keywords', $presentation->getStringId()),
            [
                'categories_keywords' => [
                    'categories' => ['invalid-category'],
                    'keywords' => 'updated keywords',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertSame('initial keywords', $presentation->getKeywords());

        $categoryNames = [];
        foreach ($presentation->getCategories() as $category) {
            $categoryNames[] = $category->getUniqueName();
        }

        self::assertSame(['energy'], $categoryNames);
    }

    public function testUpdateBusinessCardUpdatesComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = BusinessCardComponent::createNew();
        $component->setTitle('Initial Name');
        $component->setEmail1('initial@example.com');

        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('business_cards', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/projects/%s/business-cards/%s', $presentation->getStringId(), $component->getId()),
            [
                'business_card' => [
                    'title' => 'Updated Name',
                    'email1' => 'updated@example.com',
                    'tel1' => '+33 6 12 34 56 78',
                    'website1' => 'https://example.com',
                    'website2' => 'https://linkedin.com/in/example',
                    'postalMail' => '1 Rue de Test, 75000 Paris',
                    'remarks' => 'Updated note',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#businessCards-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $cards = $presentation->getOtherComponents()->getComponents('business_cards');
        $updated = null;
        foreach ($cards as $card) {
            if ($card->getId() === $component->getId()) {
                $updated = $card;
                break;
            }
        }

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated?->getTitle());
        self::assertSame('updated@example.com', $updated?->getEmail1());
        self::assertSame('https://example.com', $updated?->getWebsite1());
    }

    public function testUpdateBusinessCardRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = BusinessCardComponent::createNew();
        $component->setTitle('Initial Name');
        $component->setEmail1('initial@example.com');

        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('business_cards', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');

        $client->request(
            'POST',
            sprintf('/projects/%s/business-cards/%s', $presentation->getStringId(), $component->getId()),
            [
                'business_card' => [
                    'email1' => 'invalid-email',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s#businessCards-struct-container', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $cards = $presentation->getOtherComponents()->getComponents('business_cards');
        $updated = null;
        foreach ($cards as $card) {
            if ($card->getId() === $component->getId()) {
                $updated = $card;
                break;
            }
        }

        self::assertNotNull($updated);
        self::assertSame('initial@example.com', $updated?->getEmail1());
    }

    public function testAddDocumentRejectsMissingFile(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/documents', $presentation->getStringId()),
            ['document' => ['title' => 'Document sans fichier', '_token' => $token]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getDocuments());
    }

    public function testAddDocumentRejectsEmptyTitle(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/documents', $presentation->getStringId()),
            ['document' => ['title' => ' ', '_token' => $token]],
            ['document' => ['file' => ['file' => $this->createUploadedDocument()]]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getDocuments());
    }

    public function testCreateNewsRejectsMismatchedPresentationId(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $otherPresentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/news', $presentation->getStringId()),
            [
                'news' => [
                    'textContent' => 'Actualite invalide.',
                    'presentationId' => (string) $otherPresentation->getId(),
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getNews());
    }

    public function testStructureReorderRequiresCsrf(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation);

        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/projects/%s/structure/reorder', $presentation->getStringId()),
            [
                'scope' => 'slides',
                'orderedIds' => [(string) $slide->getId()],
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testStructureReorderRejectsMissingData(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/reorder', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'slides',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Données manquantes.', $payload['error'] ?? null);
    }

    public function testStructureReorderRejectsInvalidList(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slideA = $this->createSlide($em, $presentation, 0);
        $slideB = $this->createSlide($em, $presentation, 1);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/reorder', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'slides',
                'orderedIds' => [(string) $slideA->getId()],
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('La liste des éléments est invalide.', $payload['error'] ?? null);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $positions = [];
        foreach ($presentation->getSlides() as $slide) {
            $positions[(string) $slide->getId()] = $slide->getPosition();
        }

        self::assertSame(0, $positions[(string) $slideA->getId()] ?? null);
        self::assertSame(1, $positions[(string) $slideB->getId()] ?? null);
    }

    public function testStructureReorderReordersSlidesPositions(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slideA = $this->createSlide($em, $presentation, 0);
        $slideB = $this->createSlide($em, $presentation, 1);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/reorder', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'slides',
                'orderedIds' => [(string) $slideB->getId(), (string) $slideA->getId()],
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $positions = [];
        foreach ($presentation->getSlides() as $slide) {
            $positions[(string) $slide->getId()] = $slide->getPosition();
        }

        self::assertSame(0, $positions[(string) $slideB->getId()] ?? null);
        self::assertSame(1, $positions[(string) $slideA->getId()] ?? null);
    }

    public function testStructureReorderReordersDocumentPositions(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $documentA = $this->createDocument($em, $presentation, 0);
        $documentB = $this->createDocument($em, $presentation, 1);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/reorder', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'documents',
                'orderedIds' => [(string) $documentB->getId(), (string) $documentA->getId()],
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $positions = [];
        foreach ($presentation->getDocuments() as $document) {
            $positions[(string) $document->getId()] = $document->getPosition();
        }

        self::assertSame(0, $positions[(string) $documentB->getId()] ?? null);
        self::assertSame(1, $positions[(string) $documentA->getId()] ?? null);
    }

    public function testUpdateSlideRedirectsToImageEditor(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation, 0, SlideType::IMAGE);

        $client->loginUser($owner);
        $client->request(
            'GET',
            sprintf('/projects/%s/slides/update/%s', $presentation->getStringId(), (string) $slide->getId())
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/projects/%s/slide/update-image/%s', $presentation->getStringId(), (string) $slide->getId()),
            (string) $client->getResponse()->headers->get('Location')
        );
    }

    public function testUpdateSlideRedirectsToVideoEditor(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation, 0, SlideType::YOUTUBE_VIDEO);

        $client->loginUser($owner);
        $client->request(
            'GET',
            sprintf('/projects/%s/slides/update/%s', $presentation->getStringId(), (string) $slide->getId())
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/projects/%s/slides/edit-youtube-video/%s', $presentation->getStringId(), (string) $slide->getId()),
            (string) $client->getResponse()->headers->get('Location')
        );
    }

    public function testUpdateSlideRejectsSlideFromDifferentPresentation(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $otherPresentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $otherPresentation, 0, SlideType::IMAGE);

        $client->loginUser($owner);
        $client->request(
            'GET',
            sprintf('/projects/%s/slides/update/%s', $presentation->getStringId(), (string) $slide->getId())
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateImageSlideUpdatesImage(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/slide/update-image/%s', $presentation->getStringId(), (string) $slide->getId()),
            ['image_slide' => ['caption' => 'Nouvelle legende', '_token' => $token]],
            ['image_slide' => ['imageFile' => ['file' => $this->createUploadedImage()]]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $updatedSlide = $presentation->getSlides()->first();
        self::assertNotEmpty($updatedSlide?->getImagePath());
        self::assertSame('Nouvelle legende', $updatedSlide?->getCaption());
        self::assertNotEmpty($presentation->getExtra()->getCacheThumbnailUrl());
    }

    public function testUpdateVideoSlideUpdatesUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation, 0, SlideType::YOUTUBE_VIDEO);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/slides/edit-youtube-video/%s', $presentation->getStringId(), (string) $slide->getId()),
            [
                'video_slide' => [
                    'youtubeUrl' => 'https://youtu.be/wwl05u5U9vo',
                    'caption' => 'Nouvelle video',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $updatedSlide = $presentation->getSlides()->first();
        self::assertSame('https://youtu.be/wwl05u5U9vo', $updatedSlide?->getYoutubeUrl());
        self::assertSame('Nouvelle video', $updatedSlide?->getCaption());
    }

    public function testUpdateVideoSlideRejectsInvalidUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation, 0, SlideType::YOUTUBE_VIDEO);
        $slide->setYoutubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $em->flush();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/%s/slides/edit-youtube-video/%s', $presentation->getStringId(), (string) $slide->getId()),
            [
                'video_slide' => [
                    'youtubeUrl' => 'invalid',
                    '_token' => $token,
                ],
            ]
        );

        self::assertResponseIsSuccessful();

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $updatedSlide = null;
        foreach ($presentation->getSlides() as $existing) {
            if ($existing->getId() === $slide->getId()) {
                $updatedSlide = $existing;
                break;
            }
        }

        self::assertNotNull($updatedSlide);
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $updatedSlide?->getYoutubeUrl());
    }

    public function testUpdateDocumentUpdatesTitle(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/documents/%s', (string) $document->getId()),
            ['document' => ['title' => 'Document mis a jour', '_token' => $token]]
        );

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $updatedDocument = $presentation->getDocuments()->first();
        self::assertSame('Document mis a jour', $updatedDocument?->getTitle());
    }

    public function testUpdateDocumentRejectsInvalidTitle(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);
        $originalTitle = $document->getTitle();

        $client->loginUser($owner);
        $client->setServerParameter('HTTP_REFERER', 'http://localhost/');
        $token = $this->getCsrfToken($client, 'submit');
        $client->request(
            'POST',
            sprintf('/projects/documents/%s', (string) $document->getId()),
            ['document' => ['title' => 'a', '_token' => $token]]
        );

        self::assertResponseStatusCodeSame(302);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        $updatedDocument = $presentation->getDocuments()->first();
        self::assertSame($originalTitle, $updatedDocument?->getTitle());
    }

    public function testAddImageSlideIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-image-slide', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testAddVideoSlideIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-video-slide', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testAddDocumentIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/documents', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getDocuments());
    }

    public function testCreateNewsIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/news', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getNews());
    }

    public function testUpdateCategoriesKeywordsIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request(
            'POST',
            sprintf('/projects/%s/categories-keywords', $presentation->getStringId())
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testAddLogoIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-logo', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertNull($presentation->getLogo());
    }

    public function testUpdateLogoIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $presentation->setLogo('old-logo.png');
        $em->flush();
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('GET', sprintf('/project/%s/update/logo', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertSame('old-logo.png', $presentation->getLogo());
    }

    public function testAddWebsiteIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-website', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('websites'));
    }

    public function testAddQuestionAnswerIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-question-answer', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('questions_answers'));
    }

    public function testAddBusinessCardIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('POST', sprintf('/projects/%s/add-business-card', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('business_cards'));
    }

    public function testEditThumbnailIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request(
            'GET',
            sprintf('/projects/%s/edit/thumbnail', $presentation->getStringId())
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateSlideIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request(
            'GET',
            sprintf('/projects/%s/slides/update/%s', $presentation->getStringId(), (string) $slide->getId())
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateBusinessCardIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $component = BusinessCardComponent::createNew();
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('business_cards', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($viewer);
        $client->request(
            'GET',
            sprintf('/projects/%s/business-cards/%s', $presentation->getStringId(), $component->getId())
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateDocumentIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('GET', sprintf('/projects/documents/%s', (string) $document->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testStructureDeleteIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);

        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                'scope' => 'documents',
                'id' => (string) $document->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testStructureDeleteRequiresCsrf(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);

        $client->loginUser($owner);

        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                'scope' => 'documents',
                'id' => (string) $document->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testStructureDeleteRemovesSlide(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $slide = $this->createSlide($em, $presentation);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'slides',
                'id' => (string) $slide->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testStructureDeleteRemovesDocument(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $document = $this->createDocument($em, $presentation);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'documents',
                'id' => (string) $document->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getDocuments());
    }

    public function testStructureDeleteRemovesNews(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $news = $this->createNews($em, $presentation, $owner);

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'news',
                'id' => (string) $news->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getNews());
    }

    public function testStructureDeleteRemovesWebsiteComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = WebsiteComponent::createNew('Example', 'https://example.test');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('websites', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'websites',
                'id' => $component->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('websites'));
    }

    public function testStructureDeleteRemovesQuestionAnswerComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = QuestionAnswerComponent::createNew('Question de test', 'Réponse de test');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('questions_answers', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'questionsAnswers',
                'id' => $component->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('questions_answers'));
    }

    public function testStructureDeleteRemovesBusinessCardComponent(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = BusinessCardComponent::createNew();
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('business_cards', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);

        $token = $this->getCsrfToken($client, 'pp_structure_mutation');
        $client->request(
            'POST',
            sprintf('/projects/%s/structure/delete', $presentation->getStringId()),
            [
                '_token' => $token,
                'scope' => 'businessCards',
                'id' => $component->getId(),
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'] ?? false);

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getOtherComponents()->getComponents('business_cards'));
    }

    private function fetchPresentation(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $stringId): PPBase
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $presentation = $em->getRepository(PPBase::class)->findOneBy(['stringId' => $stringId]);

        self::assertNotNull($presentation);

        return $presentation;
    }

    private function createUploadedImage(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAEklEQVR4nGNgYGD4DwABBAEAAP8d5xgAAAAASUVORK5CYII=',
            true
        );

        $path = tempnam(sys_get_temp_dir(), 'pp_image_');
        file_put_contents($path, $png ?: '');

        return new UploadedFile($path, 'slide.png', 'image/png', null, true);
    }

    private function createUploadedLargeImage(int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pp_large_');

        if (class_exists(\Imagick::class)) {
            $image = new \Imagick();
            $image->newImage($width, $height, new \ImagickPixel('white'));
            $image->setImageFormat('png');
            $image->writeImage($path);
            $image->clear();
            $image->destroy();
        } elseif (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white);
            imagepng($image, $path);
            imagedestroy($image);
        } else {
            if (is_file($path)) {
                unlink($path);
            }
            $this->markTestSkipped('Image extension not available for generating large images.');
        }

        return new UploadedFile($path, 'large.png', 'image/png', null, true);
    }

    private function createUploadedDocument(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pp_doc_');
        file_put_contents($path, "Document de test\n");

        return new UploadedFile($path, 'document.txt', 'text/plain', null, true);
    }

    private function createSlide(
        EntityManagerInterface $em,
        PPBase $presentation,
        int $position = 0,
        SlideType $type = SlideType::IMAGE
    ): Slide
    {
        $slide = (new Slide())
            ->setType($type)
            ->setPosition($position);

        $presentation->addSlide($slide);

        $em->persist($slide);
        $em->flush();

        return $slide;
    }

    private function createDocument(EntityManagerInterface $em, PPBase $presentation, ?int $position = null): Document
    {
        $document = (new Document())
            ->setTitle('Document test')
            ->setFileName('document.pdf');

        if ($position !== null) {
            $document->setPosition($position);
        }

        $presentation->addDocument($document);

        $em->persist($document);
        $em->flush();

        return $document;
    }

    private function createNews(EntityManagerInterface $em, PPBase $presentation, \App\Entity\User $creator): News
    {
        $news = (new News())
            ->setTextContent('News test')
            ->setProject($presentation)
            ->setCreator($creator);

        $em->persist($news);
        $em->flush();

        return $news;
    }
}
