<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResetUserPasswordController extends AbstractController
{
    /*
    Allow user to request a new password : user provides their email then receives an email with reset password token. 
    */
    #[Route('/reset-password-request', name: 'reset_password_request')]
    public function resetPasswordRequest(): Response
    {

        //to fill


        return $this->render('reset_user_password/index.html.twig', [
            'controller_name' => 'ResetUserPasswordController',
        ]);


    }

    /*
    Allow user to create a new password
    */
    #[Route('/reset-password-create-new/{token}', name: 'reset_password_create_new')]
    public function forgottenPasswordCreateNew(): Response
    {

        //to fill


        return $this->render('reset_user_password/index.html.twig', [
            'controller_name' => 'ResetUserPasswordController',
        ]);


    }




}
