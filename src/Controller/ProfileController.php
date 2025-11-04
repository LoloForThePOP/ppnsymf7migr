<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Form\UserAccountEmailType;
use Symfony\Component\Form\FormError;
use App\Form\Password\UpdateAccountPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


final class ProfileController extends AbstractController
{


    #[Route('/user/{usernameSlug}', name: 'user_profile_show')]
    public function show(
    #[MapEntity(mapping: ['usernameSlug' => 'usernameSlug'])] User $user
    ): Response
    {
        return $this->render('user/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'public_profile_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $profile = $user->getProfile();

        $profileForm = $this->createForm(ProfileType::class, $profile);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $profile->setUser($this->getUser());
            $em->persist($profile);
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');

            return $this->redirectToRoute('user_profile_show', [
                'usernameSlug' => $user->getUsernameSlug(),
            ]);
        }


        return $this->render('/user/profile/edit.html.twig',[
     
            'profileForm' => $profileForm->createView(),
            'profile' => $profile,
            
        ]);

        
    }


    #[Route('account/update/menu', name: 'update_account_menu', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function accessUpdateAccountMenu(): Response
    {
        return $this->render('user/account/update_menu.html.twig');
    }    




    #[Route('account/update/email', name: 'update_account_email', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateEmail(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserAccountEmailType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'L\'adresse e-mail associée à votre compte a été modifiée.');

            return $this->redirectToRoute('user_profile_show', [
                'usernameSlug' => $user->getUsernameSlug(),
            ]);
        }

        return $this->render('user/account/update_email.html.twig', [
            'form' => $form->createView(),
        ]);

    }



    #[Route('account/update/password', name: 'update_account_password', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(UpdateAccountPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$hasher->isPasswordValid($user, $form->get('oldPassword')->getData())) {
                $form->get('oldPassword')->addError(new FormError('Mot de passe actuel incorrect.'));
            } else {
                $newPassword = $form->get('newPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $newPassword));
                $em->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');

                return $this->redirectToRoute('homepage');
            }
        }

        return $this->render('user/account/update_password.html.twig', [
            'form' => $form->createView(),
        ]);





        // to fill : soft user deletion





















    }

































    
}