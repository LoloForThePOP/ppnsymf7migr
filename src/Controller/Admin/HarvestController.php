<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\ScraperAccessVoter;

#[Route('/admin/harvest', name: 'admin_harvest', methods: ['GET'])]
#[IsGranted(ScraperAccessVoter::ATTRIBUTE)]
class HarvestController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('admin/harvest.html.twig');
    }
}
