<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UrlHarvestControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testUnsafeUrlIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('POST', '/admin/project/harvest-urls', [
            'urls' => "http://127.0.0.1\n",
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'URL non autorisée.',
            (string) $client->getResponse()->getContent()
        );
    }
}
