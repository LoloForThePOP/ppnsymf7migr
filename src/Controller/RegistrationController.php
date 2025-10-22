<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationController extends AbstractController
{

    public function __construct(private MailerInterface $mailer) {}


    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $user->getEmailValidationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // to fill : passer par une page intermédiaire : un e-mail vous a été envoyé à l'adresse ...

        $email = (new Email())
            ->from($this->getParameter('app.email.noreply'))
            ->to($user->getEmail())
            ->subject('Propon veuillez confirmer votre adresse e-mail')
            ->html("
                <p>Bonjour {$user->getUsername()},</p>
                <p>Pour finaliser votre inscription sur Propon veuillez cliquer sur le lien ci-dessous :</p>
                <p><a href='{$verificationUrl}'>{$verificationUrl}</a></p>
                <p>Si vous n'avez pas créé ce compte Propon veuillez ignorer ce message.</p>
            ");

        $this->mailer->send($email);
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

            $token = bin2hex(random_bytes(16));

            $user->setEmailValidationToken($token);

            $em->persist($user);
            $em->flush();

            $this->sendVerificationEmail($user);

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Veuillez aller dans votre boîte e-mail pour le valider.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }


    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['emailValidationToken' => $token]);

        if (!$user) {
            $this->addFlash('danger', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setEmailValidationToken(null);
        $em->flush();

        $this->addFlash('success', 'Votre adresse e-mail a été vérifiée avec succès vous pouvez maintenant vous connecter !');
        return $this->redirectToRoute('app_login');
    }








}
