<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

public function __construct(
    private UrlGeneratorInterface $urlGenerator,
    private \App\Repository\UserRepository $userRepository
) {}


   public function authenticate(Request $request): Passport
{
    $email = $request->getPayload()->getString('email');
    $password = $request->getPayload()->getString('password');

    $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

    return new Passport(
        new UserBadge($email, function (string $userIdentifier) {
            $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Pas de compte trouvé à cette adresse e-mail.');
            }

            if (!$user->isActive()) {
                throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé.');
            }

            if (!$user->isVerified()) {
                throw new CustomUserMessageAuthenticationException('Merci de vérifier votre boîte mail pour confirmer votre inscription.');
            }

            if ($user->getGoogleId() && !$user->getPassword()) {
                throw new CustomUserMessageAuthenticationException(
                    'Merci de vous connecter avec Google, pas avec un mot de passe.'
                );
            }


            if ($user->getFacebookId() && !$user->getPassword()) {
                throw new CustomUserMessageAuthenticationException(
                    'Merci de vous connecter avec Facebook, pas avec un mot de passe.'
                );
            }


            return $user;
        }),
        new PasswordCredentials($password),
        [
            new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
            new RememberMeBadge(),
        ]
    );
}


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // For example:
        // return new RedirectResponse($this->urlGenerator->generate('some_route'));// Redirect to homepage after login
        return new RedirectResponse($this->urlGenerator->generate('homepage'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}