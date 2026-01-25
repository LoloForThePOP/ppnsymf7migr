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
use App\Security\Voter\ScraperAccessVoter;

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
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::linkToRoute('📈 Monitoring', 'fa fa-chart-line', 'admin_monitoring');
        }
        if ($this->isGranted(ScraperAccessVoter::ATTRIBUTE)) {
            yield MenuItem::linkToRoute('✨ Outils de collecte', 'fa fa-magic', 'admin_harvest');
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::linkToCrud('📁 Projets', 'fa fa-folder-open', PPBase::class);
            yield MenuItem::linkToCrud('👥 Utilisateurs', 'fa fa-user', User::class);
        }
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::linkToRoute('🧐 À vérifier', 'fa fa-check-circle', 'admin_presentations_to_review');
            yield MenuItem::linkToRoute('🧰 Divers', 'fa fa-toolbox', 'admin_misc');
        }
    }
}
