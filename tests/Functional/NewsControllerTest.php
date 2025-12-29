<?php

namespace App\Tests\Functional;

use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class NewsControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    private function createNews(EntityManagerInterface $em, PPBase $project, User $creator): News
    {
        $news = (new News())
            ->setProject($project)
            ->setCreator($creator)
            ->setTextContent('Initial news content');

        $em->persist($news);
        $em->flush();

        return $news;
    }

    public function testOwnerCanUpdateNews(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $news = $this->createNews($em, $project, $owner);

        $client->loginUser($owner);

        $crawler = $client->request('GET', sprintf('/news/edit/%d', $news->getId()));
        $form = $crawler->selectButton('Valider')->form();
        $form['news[textContent]'] = 'Updated news content';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/%s', $project->getStringId()),
            (string) $client->getResponse()->headers->get('Location')
        );
        self::assertStringContainsString(
            'news-struct-container',
            (string) $client->getResponse()->headers->get('Location')
        );

        $em->clear();
        $updated = $em->getRepository(News::class)->find($news->getId());
        self::assertSame('Updated news content', $updated?->getTextContent());
    }

    public function testNonOwnerIsForbiddenFromEditingNews(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createUser($em);
        $project = $this->createProject($em, $owner);
        $news = $this->createNews($em, $project, $owner);
        $otherUser = $this->createUser($em);

        $client->loginUser($otherUser);

        $client->request('GET', sprintf('/news/edit/%d', $news->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
