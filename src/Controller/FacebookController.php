<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FacebookController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process
     */

    #[Route('/connect/facebook', name: 'connect_facebook_start')]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        // define the scopes here, not in the config
        return $clientRegistry
            ->getClient('facebook')
            ->redirect(['email'], []);
    }

    /**
     * After going to Facebook, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml
     */
    #[Route('/connect/facebook/check', name: 'connect_facebook_check')]
    public function connectCheckAction(): void
    {
        // Symfony Security will handle this automatically.
        // This method must remain blank.
        throw new \LogicException('This should never be reached!');
    }
}
