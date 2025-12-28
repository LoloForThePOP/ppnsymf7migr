<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Form\Password\ForgottenPasswordEmailType;
use App\Service\SlugService;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationController extends AbstractController
{

    public function __construct(
        
        private MailerInterface $mailer, 
        private SlugService $slugger,
        private UserRepository $userRepository

    ) {}


    private function sendVerificationEmail(User $user, string $token): void
    {
        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->getParameter('app.email.noreply'), 'Propon'))
            ->to(new Address($user->getEmail()))
            ->subject('Propon - Confirmez votre adresse e-mail')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'recipientName' => $user->getUsername(),
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    private function issueEmailVerificationToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setEmailValidationToken(hash('sha256', $token));
        $user->setEmailValidationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        return $token;
    }


    #[Route('/registration', name: 'app_registration')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($hashedPassword);
            $user->setIsActive(true);
            $user->setIsVerified(false);

            $token = $this->issueEmailVerificationToken($user);
            //$this->slugger->generate($user);

            try {
                $em->persist($user);
                //dd($user);
                $em->flush();
            } catch (UniqueConstraintViolationException $exception) {
                $errorMessage = (string) $exception->getMessage();
                if (str_contains($errorMessage, 'UNIQ_IDENTIFIER_EMAIL')) {
                    $form->get('email')->addError(new FormError('Cette adresse e-mail est déjà utilisée.'));
                } elseif (str_contains($errorMessage, 'UNIQ_IDENTIFIER_USERNAME')) {
                    $form->get('username')->addError(new FormError('Ce nom d\'utilisateur est déjà utilisé.'));
                } else {
                    $form->addError(new FormError('Ce compte existe déjà.'));
                }

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            $this->sendVerificationEmail($user, $token);

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Veuillez aller dans votre boîte e-mail pour le valider.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }


    #[Route('/verify-email/{token}', name: 'app_verify_email', requirements: ['token' => '[A-Fa-f0-9]{64}'])]
    public function verifyEmail(string $token, EntityManagerInterface $em): Response
    {
        $tokenHash = hash('sha256', $token);
        $user = $em->getRepository(User::class)->findOneBy(['emailValidationToken' => $tokenHash]);

        if (!$user) {
            $this->addFlash('danger', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $expiresAt = $user->getEmailValidationTokenExpiresAt();
        if ($expiresAt === null || $expiresAt < new \DateTimeImmutable()) {
            $newToken = $this->issueEmailVerificationToken($user);
            $em->flush();
            $this->sendVerificationEmail($user, $newToken);
            $this->addFlash('success', 'Lien expiré. Un nouvel e-mail de vérification vient de vous être envoyé.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setEmailValidationToken(null);
        $user->setEmailValidationTokenExpiresAt(null);
        $em->flush();

        $this->addFlash('success', 'Votre adresse e-mail a été vérifiée avec succès vous pouvez maintenant vous connecter !');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify-email/resend', name: 'app_verify_email_resend', methods: ['GET', 'POST'])]
    public function resendVerification(
        Request $request,
        EntityManagerInterface $em,
        #[Autowire(service: 'limiter.verify_email_resend_ip')] RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.verify_email_resend_email')] RateLimiterFactory $emailLimiter,
        LoggerInterface $logger,
    ): Response
    {
        $form = $this->createForm(ForgottenPasswordEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $normalizedEmail = strtolower(trim($email));
            $ip = $request->getClientIp() ?? 'unknown';

            $ipLimit = $ipLimiter->create($ip)->consume(1);
            $emailLimit = $emailLimiter->create(hash('sha256', $normalizedEmail))->consume(1);
            $ipAccepted = $ipLimit->isAccepted();
            $emailAccepted = $emailLimit->isAccepted();

            if (!$ipAccepted || !$emailAccepted) {
                $logger->warning('Rate limit hit for verification email resend.', [
                    'ip' => $ip,
                    'email_hash' => hash('sha256', $normalizedEmail),
                    'ip_accepted' => $ipAccepted,
                    'email_accepted' => $emailAccepted,
                ]);

                $this->addFlash('danger', 'Trop de tentatives. Veuillez réessayer plus tard.');

                return $this->render('registration/resend_verification.html.twig', [
                    'form' => $form->createView(),
                ], new Response('', Response::HTTP_TOO_MANY_REQUESTS));
            }

            $user = $this->userRepository->findOneByEmail($email);

            if ($user && !$user->isVerified()) {
                $token = $this->issueEmailVerificationToken($user);
                $em->flush();
                $this->sendVerificationEmail($user, $token);
            }

            $this->addFlash('success', 'Si un compte non vérifié correspond à cette adresse, un e-mail a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/resend_verification.html.twig', [
            'form' => $form->createView(),
        ]);
    }








}
