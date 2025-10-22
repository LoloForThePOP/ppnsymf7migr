<?php


namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process
    */

    #[Route('/connect/google', name: 'connect_google_start')]
        public function connect(ClientRegistry $clientRegistry)
        {
            // define the scopes here, not in the config
            return $clientRegistry
                ->getClient('google')
                ->redirect(['email', 'profile'], []);
        }

    /**
     * After going to Google, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(Request $request, ClientRegistry $clientRegistry): void
    {
        // Symfony Security will handle this automatically.
        // This method must remain blank.
        throw new \LogicException('This should never be reached!');
    }
}
