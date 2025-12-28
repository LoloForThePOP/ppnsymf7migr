<?php

namespace App\Tests\Functional;

use App\Entity\Embeddables\GeoPoint;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PlaceControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testCreatePlacePersistsAndReturnsId(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);

        $client->loginUser($creator);

        $token = $this->getCsrfToken($client, 'pp_place_mutation');

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-new-place', $project->getStringId()),
            [
                '_token' => $token,
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'type' => 'city',
                'name' => 'Paris',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['feedbackCode'] ?? false);
        self::assertIsInt($payload['placeId'] ?? null);

        $em->clear();
        $place = $em->getRepository(Place::class)->find($payload['placeId']);
        self::assertNotNull($place);
        self::assertSame($project->getId(), $place->getProject()?->getId());
        self::assertSame('Paris', $place->getName());
    }

    public function testDeletePlaceRemoves(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);

        $place = (new Place())
            ->setType('city')
            ->setName('Paris')
            ->setGeoloc(new GeoPoint(48.8566, 2.3522));

        $project->addPlace($place);
        $em->persist($place);
        $em->flush();

        $placeId = $place->getId();

        $client->loginUser($creator);

        $token = $this->getCsrfToken($client, 'pp_place_mutation');

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-remove-place', $project->getStringId()),
            [
                '_token' => $token,
                'placeId' => $placeId,
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($placeId, $payload['deletedPlaceId'] ?? null);
        self::assertTrue($payload['feedbackCode'] ?? false);

        $em->clear();
        self::assertNull($em->getRepository(Place::class)->find($placeId));
    }

    public function testMissingCsrfIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);

        $client->loginUser($creator);

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-new-place', $project->getStringId()),
            [
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'type' => 'city',
                'name' => 'Paris',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Jeton CSRF invalide.', $payload['error'] ?? null);
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $creator = $this->createUser($em);
        $project = $this->createProject($em, $creator);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $client->request(
            'POST',
            sprintf('/projects/%s/places/ajax-new-place', $project->getStringId()),
            [
                'latitude' => '48.8566',
                'longitude' => '2.3522',
                'type' => 'city',
                'name' => 'Paris',
            ],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
    }
}
