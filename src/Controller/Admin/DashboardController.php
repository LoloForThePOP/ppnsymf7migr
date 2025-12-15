<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use App\Entity\User;
use App\Controller\Admin\PPBaseCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(PPBaseCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
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
