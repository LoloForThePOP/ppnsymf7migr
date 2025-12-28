<?php

namespace App\Tests\Functional;

use App\Entity\PPBase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

trait FunctionalTestHelper
{
    protected function createUser(EntityManagerInterface $em, array $roles = []): User
    {
        $user = (new User())
            ->setEmail(sprintf('user+%s@example.com', uniqid('', true)))
            ->setUsername(sprintf('user_%s', uniqid('', true)))
            ->setPassword('dummy')
            ->setIsActive(true)
            ->setIsVerified(true);

        if ($roles !== []) {
            $user->setRoles($roles);
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createProject(EntityManagerInterface $em, User $creator, ?string $goal = null): PPBase
    {
        $project = (new PPBase())
            ->setGoal($goal ?? 'Test goal for project presentation.')
            ->setCreator($creator);

        $em->persist($project);
        $em->flush();

        return $project;
    }

    protected function getCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $container = $client->getContainer();
        $session = $container->get('session.factory')->createSession();

        $cookie = $client->getCookieJar()->get($session->getName());
        if ($cookie !== null) {
            $session->setId($cookie->getValue());
        }

        $session->start();

        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $token = $container->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
            $session->save();
        } finally {
            $requestStack->pop();
        }

        return $token;
    }
}
