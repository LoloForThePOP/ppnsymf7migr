<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_harvest');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('📊 Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('✨ Outils de collecte', 'fa fa-magic', 'admin_harvest');
        yield MenuItem::linkToCrud('📁 Projets', 'fa fa-folder-open', PPBase::class);
        yield MenuItem::linkToCrud('👥 Utilisateurs', 'fa fa-user', User::class);
    }
}
