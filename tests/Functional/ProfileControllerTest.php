<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProfileControllerTest extends WebTestCase
{
    use ResetDatabase;
    use FunctionalTestHelper;

    public function testProfileEditRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profile/edit');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testProfileShowIsPublic(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);

        $client->request('GET', sprintf('/user/%s', $user->getUsernameSlug()));

        self::assertResponseIsSuccessful();
    }

    public function testProfileEditUpdatesProfile(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['profile[description]'] = 'Bio de test';
        $form['profile[website1]'] = 'https://example.com';
        $form['profile[tel1]'] = '+33 6 12 34 56 78';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/user/%s', $user->getUsernameSlug()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        $profile = $reloaded?->getProfile();
        self::assertSame('Bio de test', $profile?->getDescription());
        self::assertSame('https://example.com', $profile?->getWebsite1());
        self::assertSame('+33 6 12 34 56 78', $profile?->getTel1());
    }

    public function testProfileEditRejectsInvalidPhone(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $profile = $user->getProfile();
        $profile?->setTel1('+33 6 12 34 56 78');
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['profile[tel1]'] = 'invalid-phone';

        $client->submit($form);

        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertSame('+33 6 12 34 56 78', $reloaded?->getProfile()?->getTel1());
    }

    public function testProfileEditRejectsInvalidWebsite(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $profile = $user->getProfile();
        $profile?->setWebsite1('https://example.com');
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['profile[website1]'] = 'javascript:alert(1)';

        $client->submit($form);

        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertSame('https://example.com', $reloaded?->getProfile()?->getWebsite1());
    }

    public function testUpdateEmailRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/account/update/email');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUpdateEmailUpdatesEmail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/email');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_account_email[email]'] = 'updated@example.com';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString(
            sprintf('/user/%s', $user->getUsernameSlug()),
            (string) $client->getResponse()->headers->get('Location')
        );

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertSame('updated@example.com', $reloaded?->getEmail());
    }

    public function testUpdateEmailRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $originalEmail = $user->getEmail();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/email');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_account_email[email]'] = 'invalid-email';

        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('adresse e-mail valide', (string) $client->getResponse()->getContent());

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertSame($originalEmail, $reloaded?->getEmail());
    }

    public function testUpdateEmailRejectsDuplicateEmail(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser($em);
        $otherUser = $this->createUser($em);
        $originalEmail = $user->getEmail();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/email');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_account_email[email]'] = $otherUser->getEmail();

        $client->submit($form);

        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertSame($originalEmail, $reloaded?->getEmail());
    }

    public function testUpdatePasswordRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/account/update/password');

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUpdatePasswordRejectsInvalidOldPassword(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = $this->createUser($em);
        $user->setPassword($hasher->hashPassword($user, 'OldPass123!'));
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/password');
        $form = $crawler->selectButton('Valider')->form();
        $form['update_account_password[oldPassword]'] = 'WrongPass123!';
        $form['update_account_password[newPassword][first]'] = 'NewPass123!';
        $form['update_account_password[newPassword][second]'] = 'NewPass123!';

        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mot de passe actuel incorrect', (string) $client->getResponse()->getContent());

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertTrue($hasher->isPasswordValid($reloaded, 'OldPass123!'));
    }

    public function testUpdatePasswordRejectsMismatchedNewPassword(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = $this->createUser($em);
        $user->setPassword($hasher->hashPassword($user, 'OldPass123!'));
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/password');
        $form = $crawler->selectButton('Valider')->form();
        $form['update_account_password[oldPassword]'] = 'OldPass123!';
        $form['update_account_password[newPassword][first]'] = 'NewPass123!';
        $form['update_account_password[newPassword][second]'] = 'Mismatch123!';

        $client->submit($form);

        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertTrue($hasher->isPasswordValid($reloaded, 'OldPass123!'));
    }

    public function testUpdatePasswordUpdatesPassword(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = $this->createUser($em);
        $user->setPassword($hasher->hashPassword($user, 'OldPass123!'));
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/update/password');
        $form = $crawler->selectButton('Valider')->form();
        $form['update_account_password[oldPassword]'] = 'OldPass123!';
        $form['update_account_password[newPassword][first]'] = 'NewPass123!';
        $form['update_account_password[newPassword][second]'] = 'NewPass123!';

        $client->submit($form);

        self::assertResponseStatusCodeSame(302);

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertTrue($hasher->isPasswordValid($reloaded, 'NewPass123!'));
        self::assertFalse($hasher->isPasswordValid($reloaded, 'OldPass123!'));
    }
}
