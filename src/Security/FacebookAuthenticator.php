<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class FacebookAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_facebook_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('facebook');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
                $facebookUser = $client->fetchUserFromToken($accessToken);
                $repository = $this->entityManager->getRepository(User::class);

                $facebookId = trim((string) $facebookUser->getId());
                if ($facebookId === '') {
                    throw new CustomUserMessageAuthenticationException('Connexion Facebook impossible. Veuillez réessayer.');
                }

                $user = $repository->findOneBy(['facebookId' => $facebookId]);
                if ($user instanceof User) {
                    $this->assertUserCanAuthenticate($user);
                    return $user;
                }

                $email = trim((string) $facebookUser->getEmail());
                if ($email === '') {
                    throw new CustomUserMessageAuthenticationException(
                        'Connexion Facebook impossible: aucune adresse e-mail disponible. Utilisez la connexion par e-mail.'
                    );
                }
                $email = mb_strtolower($email);

                $user = $repository->findOneBy(['email' => $email]);
                if ($user instanceof User) {
                    if ($user->getFacebookId() !== null && $user->getFacebookId() !== $facebookId) {
                        throw new CustomUserMessageAuthenticationException('Un autre compte Facebook est déjà lié à cette adresse e-mail.');
                    }

                    $shouldFlush = false;
                    if ($user->getFacebookId() !== $facebookId) {
                        $user->setFacebookId($facebookId);
                        $shouldFlush = true;
                    }
                    if (!$user->isVerified()) {
                        $user->setIsVerified(true);
                        $shouldFlush = true;
                    }

                    if ($shouldFlush) {
                        $this->entityManager->flush();
                    }

                    $this->assertUserCanAuthenticate($user);
                    return $user;
                }

                $fullName = trim((string) $facebookUser->getName());
                if ($fullName === '') {
                    $fullName = strstr($email, '@', true) ?: 'Utilisateur Facebook';
                }

                $user = new User();
                $user->setEmail($email);
                $user->setFacebookId($facebookId);
                $user->setRoles(['ROLE_USER']);
                $user->setUsername($fullName);
                $user->setIsVerified(true);
                $user->setIsActive(true);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    private function assertUserCanAuthenticate(User $user): void
    {
        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Merci de vérifier votre boîte mail pour confirmer votre inscription.');
        }
    }
}
