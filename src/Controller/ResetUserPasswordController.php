<?php

namespace App\Controller;


use App\Repository\UserRepository;
use Symfony\Component\Mime\Address;
use App\Form\Password\{
    ForgottenPasswordEmailType,
    ForgottenPasswordCreationType,
    };
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

#[Route('/reset-password')]
final class ResetUserPasswordController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/request', name: 'reset_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        #[Autowire(service: 'limiter.reset_password_request_ip')] RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.reset_password_request_email')] RateLimiterFactory $emailLimiter,
        LoggerInterface $logger,
    ): Response
    {
        $form = $this->createForm(ForgottenPasswordEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string) $form->get('email')->getData());
            $normalizedEmail = strtolower($email);
            $ip = $request->getClientIp() ?? 'unknown';

            $ipLimit = $ipLimiter->create($ip)->consume(1);
            $emailLimit = $emailLimiter->create(hash('sha256', $normalizedEmail))->consume(1);
            $ipAccepted = $ipLimit->isAccepted();
            $emailAccepted = $emailLimit->isAccepted();

            if (!$ipAccepted || !$emailAccepted) {
                $logger->warning('Rate limit hit for password reset request.', [
                    'ip' => $ip,
                    'email_hash' => hash('sha256', $normalizedEmail),
                    'ip_accepted' => $ipAccepted,
                    'email_accepted' => $emailAccepted,
                ]);

                $this->addFlash('danger', 'Trop de tentatives. Veuillez réessayer plus tard.');

                return $this->render('user/account/reset_password/forgotten_password_request.html.twig', [
                    'form' => $form,
                ], new Response('', Response::HTTP_TOO_MANY_REQUESTS));
            }

            $user = $this->userRepository->findOneByEmail($email);

            if ($user) {
                // Generate a secure reset token
                $token = $this->tokenGenerator->generateToken();
                $tokenHash = hash('sha256', $token);

                $user->setResetPasswordToken($tokenHash);
                $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $this->entityManager->flush();

                // Generate absolute reset URL
                $url = $this->generateUrl(
                    'reset_password_create',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                // Build & send reset email
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address($this->getParameter('app.email.noreply'), 'Projet des Projets'))
                    ->to(new Address($user->getEmail()))
                    ->subject('Propon - Réinitialisation de votre mot de passe')
                    ->htmlTemplate('user/account/reset_password/email_confirm_token.html.twig')
                    ->context([
                        'confirmationURL' => $url,
                        'user' => $user,
                    ]);

                $this->mailer->send($emailMessage);
            }

            $this->addFlash(
                'success',
                'Si un compte correspond à cette adresse, un e-mail de réinitialisation vous a été envoyé.'
            );

            return $this->redirectToRoute('homepage');
        }

        return $this->render('user/account/reset_password/forgotten_password_request.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/create-new/{token}', name: 'reset_password_create', methods: ['GET', 'POST'])]
    public function createNew(
        Request $request,
        string $token,
        UserPasswordHasherInterface $hasher
    ): Response {
        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $tokenHash]);

        if (!$user) {
            $this->addFlash('danger', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $expiresAt = $user->getResetPasswordTokenExpiresAt();
        if ($expiresAt === null || $expiresAt < new \DateTimeImmutable()) {
            $user->setResetPasswordToken(null);
            $user->setResetPasswordTokenExpiresAt(null);
            $this->entityManager->flush();
            $this->addFlash('danger', 'Lien de réinitialisation expiré. Veuillez refaire une demande.');
            return $this->redirectToRoute('reset_password_request');
        }

        $form = $this->createForm(ForgottenPasswordCreationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $newPassword));
            $user->setResetPasswordToken(null);
            $user->setResetPasswordTokenExpiresAt(null);

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/account/reset_password/forgotten_password_creation.html.twig', [
            'form' => $form,
        ]);
    }
}
