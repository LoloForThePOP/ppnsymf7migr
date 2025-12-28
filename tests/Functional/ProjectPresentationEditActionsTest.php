<?php

namespace App\Tests\Functional;

use App\Entity\Document;
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
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertSame(SlideType::IMAGE, $slide->getType());
        self::assertNotEmpty($slide->getImagePath());
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
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getSlides());

        $slide = $presentation->getSlides()->first();
        self::assertInstanceOf(Slide::class, $slide);
        self::assertSame(SlideType::YOUTUBE_VIDEO, $slide->getType());
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $slide->getYoutubeUrl());
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
            sprintf('/%s', $presentation->getStringId()),
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
            sprintf('/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(1, $presentation->getNews());
        self::assertSame('Une actualité de test.', $presentation->getNews()->first()->getTextContent());
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
