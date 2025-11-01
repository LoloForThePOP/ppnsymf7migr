<?php

namespace App\Controller;


use App\Repository\UserRepository;
use Symfony\Component\Mime\Address;
use App\Form\ForgottenPasswordEmailType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ForgottenPasswordCreationType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    public function request(Request $request): Response
    {
        $form = $this->createForm(ForgottenPasswordEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $this->userRepository->findOneByEmail($email);

            if (!$user) {
                $this->addFlash('danger', 'Cette adresse e-mail est inconnue.');
                return $this->redirectToRoute('app_login');
            }

            // Generate a secure reset token
            $token = $this->tokenGenerator->generateToken();

            $user->setResetPasswordToken($token);
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

            $this->addFlash(
                'success',
                'Un e-mail de réinitialisation vous a été envoyé. Consultez votre boîte mail.'
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
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $token]);

        if (!$user) {
            $this->addFlash('danger', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ForgottenPasswordCreationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $newPassword));
            $user->setResetPasswordToken(null);

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/account/reset_password/forgotten_password_creation.html.twig', [
            'form' => $form,
        ]);
    }
}
