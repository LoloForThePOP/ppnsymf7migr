<?php

namespace App\Tests\Functional;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectPresentationCreateEditTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/create-project-presentation/0');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testCreateCreatesPresentationAndRedirectsToNextStep(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/create-project-presentation/0');
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[goal]'] = 'A test goal for creation flow.';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/create-project-presentation/1/', $location);

        $path = parse_url($location, PHP_URL_PATH);
        $parts = $path !== null ? explode('/', trim($path, '/')) : [];
        $stringId = $parts[count($parts) - 1] ?? null;

        self::assertNotEmpty($stringId);

        $presentation = $em->getRepository(PPBase::class)->findOneBy(['stringId' => $stringId]);
        self::assertNotNull($presentation);
        self::assertSame($user->getUserIdentifier(), $presentation->getCreator()?->getUserIdentifier());
        self::assertSame('A test goal for creation flow.', $presentation->getGoal());
    }

    public function testCreateStepIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $client->request('GET', sprintf('/create-project-presentation/0/%s', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testTextDescriptionStepUpdatesAndRedirects(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/1/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[textDescription]'] = "Line one\nLine two";

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/create-project-presentation/2/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertStringContainsString('Line one', (string) $presentation->getTextDescription());
        self::assertStringContainsString('<br', (string) $presentation->getTextDescription());
        self::assertStringContainsString('Line two', (string) $presentation->getTextDescription());
    }

    public function testInitialStatusStepRejectsInvalidStatus(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/2/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[initialStatus]'] = 'invalid-status';

        $client->submit($form);

        self::assertResponseStatusCodeSame(403);
    }

    public function testInitialStatusStepPersistsValidStatus(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/2/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[initialStatus]'] = 'idea';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/create-project-presentation/3/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertSame(['idea'], $presentation->getStatuses());
    }

    public function testImageSlideStepAllowsEmptyUpload(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/3/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/create-project-presentation/4/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertCount(0, $presentation->getSlides());
    }

    public function testTitleStepUpdatesAndRedirects(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/4/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[title]'] = 'Test Project Title';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/create-project-presentation/5/%s', $presentation->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertSame('Test Project Title', $presentation->getTitle());
    }

    public function testFinalStepCompletesCreationAndRedirects(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);

        $crawler = $client->request(
            'GET',
            sprintf('/create-project-presentation/5/%s', $presentation->getStringId())
        );
        $form = $crawler->selectButton('Continuer')->form();
        $form['project_presentation_creation[keywords]'] = 'test, wizard';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertSame(
            sprintf('/%s', $presentation->getStringId()),
            (string) parse_url((string) $client->getResponse()->headers->get('Location'), PHP_URL_PATH)
        );

        $presentation = $this->fetchPresentation($client, $presentation->getStringId());
        self::assertTrue((bool) $presentation->isCreationFormCompleted());
    }

    public function testEditShowOwnerSeesEditionMode(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $client->loginUser($owner);
        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('in-edition-mode', (string) $client->getResponse()->getContent());
    }

    public function testEditShowPublishedNonOwnerSeesConsultationMode(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $viewer = $this->createUser($em);

        $client->loginUser($viewer);
        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('in-consultation-mode', (string) $client->getResponse()->getContent());
    }

    public function testEditShowUnpublishedIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);
        $presentation->setIsPublished(false);
        $em->flush();

        $viewer = $this->createUser($em);
        $client->loginUser($viewer);

        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseStatusCodeSame(403);
    }

    private function fetchPresentation(KernelBrowser $client, string $stringId): PPBase
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $presentation = $em->getRepository(PPBase::class)->findOneBy(['stringId' => $stringId]);

        self::assertNotNull($presentation);

        return $presentation;
    }
}
