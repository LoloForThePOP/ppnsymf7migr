<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SlugGenerationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSlugIsGeneratedForUser(): void
    {
        $user = (new User())
            ->setUsername('John Doe')
            ->setEmail('john@example.com')
            ->setPassword('dummy');

        $this->em->persist($user);
        $this->em->flush();

        self::assertNotNull($user->getUsernameSlug(), 'Slug should be generated.');
        self::assertSame('john-doe', $user->getUsernameSlug());
    }

    public function testSlugIsGeneratedForPPBase(): void
    {
        $user = (new User())
            ->setUsername('Test User')
            ->setEmail('test@example.com')
            ->setPassword('dummy');

        $this->em->persist($user);

        $ppBase = (new PPBase())
            ->setTitle('Solar Panel Project')
            ->setCreator($user)   
            ->setGoal('Build renewable energy sources');

        $this->em->persist($ppBase);
        $this->em->flush();

        self::assertNotNull($ppBase->getStringId(), 'Slug should be generated.');
        self::assertSame('solar-panel-project', $ppBase->getStringId());
    }

    /*public function testSlugIncrementsWhenDuplicate(): void
    {
        $first = (new PPBase())->setTitle('Duplicate Test');
        $second = (new PPBase())->setTitle('Duplicate Test');

        $this->em->persist($first);
        $this->em->persist($second);
        $this->em->flush();

        self::assertSame('duplicate-test', $first->getStringId());
        self::assertSame('duplicate-test-1', $second->getStringId());
    }*/

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        unset($this->em);
    }
}
