<?php

namespace App\Tests\Functional;

use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectPresentationUrlRenderingTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testWebsiteLinkOmitsUnsafeUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = WebsiteComponent::createNew('Example', 'javascript:alert(1)');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('websites', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'href="javascript:alert(1)"',
            (string) $client->getResponse()->getContent()
        );
    }

    public function testWebsiteLinkRendersSafeUrl(): void
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
        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'href="https://example.test"',
            (string) $client->getResponse()->getContent()
        );
    }

    public function testBusinessCardLinkOmitsUnsafeUrl(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $presentation = $this->createProject($em, $owner);

        $component = BusinessCardComponent::createNew();
        $component->setWebsite1('javascript:alert(1)');
        $otherComponents = $presentation->getOtherComponents();
        $otherComponents->addComponent('business_cards', $component);
        $presentation->setOtherComponents($otherComponents);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', sprintf('/%s', $presentation->getStringId()));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'href="javascript:alert(1)"',
            (string) $client->getResponse()->getContent()
        );
    }
}
