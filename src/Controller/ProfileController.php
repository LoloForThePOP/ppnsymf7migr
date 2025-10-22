<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/user')]
final class ProfileController extends AbstractController
{


    #[Route('/{usernameSlug}', name: 'user_profile_show')]
    public function show(
        #[MapEntity(mapping: ['usernameSlug' => 'usernameSlug'])] User $user
    ): Response
    {
        return $this->render('user_profile/index.html.twig', [
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


        return $this->render('/user_profile/edit.html.twig',[
     
            'profileForm' => $profileForm->createView(),
            'profile' => $profile,
            
        ]);

        
    }
    
}