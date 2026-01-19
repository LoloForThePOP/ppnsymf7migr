<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/misc', name: 'admin_misc', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
class MiscController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('admin/misc.html.twig');
    }
}
