<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Service\SlugService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationController extends AbstractController
{

    public function __construct(
        
        private MailerInterface $mailer, 
        private SlugService $slugger

    ) {}


    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $user->getEmailValidationToken()],
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
