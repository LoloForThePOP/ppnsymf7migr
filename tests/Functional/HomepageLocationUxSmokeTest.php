<?php

namespace App\Tests\Functional;

use App\Entity\Embeddables\GeoPoint;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Zenstruck\Foundry\Test\ResetDatabase;

final class HomepageLocationUxSmokeTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testAnonymousWithoutLocationShowsFirstTimePrompt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString(
            'Activez votre localisation ou choisissez un lieu pour découvrir des projets proches.',
            $content
        );
        self::assertStringContainsString('Choisir un lieu', $content);
    }

    public function testAnonymousWithLocationCookieShowsAroundYouRailAndModifier(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator, 'Projet local autour de Paris pour test homepage.');
        $this->attachPlace($em, $project->getId(), 48.8566, 2.3522, 'Paris');

        $client->getCookieJar()->set(new Cookie('search_pref_location', rawurlencode('48.8566|2.3522|12')));
        $client->getCookieJar()->set(new Cookie('search_pref_location_label', rawurlencode('Paris, France')));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString(
            'Activez votre localisation ou choisissez un lieu pour découvrir des projets proches.',
            $content
        );
        self::assertStringContainsString('id="home-around-you-rail"', $content);
        self::assertStringContainsString('data-home-location-trigger', $content);
        self::assertStringContainsString('Paris, France · 12 km', $content);
    }

    public function testHomepageRendersLocationModalControlsForUpdateAndReset(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator, 'Projet test modal localisation.');
        $this->attachPlace($em, $project->getId(), 43.2965, 5.3698, 'Marseille');

        $client->getCookieJar()->set(new Cookie('search_pref_location', rawurlencode('43.2965|5.3698|8')));
        $client->getCookieJar()->set(new Cookie('search_pref_location_label', rawurlencode('Marseille, France')));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('id="home-location-modal"', $content);
        self::assertStringContainsString('data-home-location-picker', $content);
        self::assertStringContainsString('data-location-reset', $content);
        self::assertStringContainsString('data-home-location-close', $content);
    }

    private function attachPlace(EntityManagerInterface $em, int $projectId, float $lat, float $lng, string $label): void
    {
        $project = $em->getRepository(\App\Entity\PPBase::class)->find($projectId);
        if ($project === null) {
            self::fail('Project not found while attaching place.');
        }

        $place = (new Place())
            ->setName($label)
            ->setLocality($label)
            ->setCountry('France')
            ->setType('city')
            ->setProject($project)
            ->setGeoloc((new GeoPoint())->setLatitude($lat)->setLongitude($lng));

        $project->addPlace($place);
        $em->persist($place);
        $em->flush();
    }
}
