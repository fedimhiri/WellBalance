<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DocumentRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin-ea', routeName: 'admin_ea')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
    ) {
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('WellBalance — Administration');
    }

    public function configureMenuItems(): iterable
    {
        $totalDocuments = $this->documentRepository->countAll();
        $totalAnalyzed = $this->documentRepository->countAnalyzed();
        $totalInsurance = $this->documentRepository->countInsuranceSubmitted();
        $totalAnomalies = $this->documentRepository->countAnomalies();

        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Documents');
        yield MenuItem::linkToCrud('Documents', 'fa fa-file-pdf', \App\Entity\Document::class)
            ->setBadge((string) $totalDocuments, 'primary');
        yield MenuItem::linkToCrud('Catégories', 'fa fa-folder', \App\Entity\CategorieDocument::class);

        yield MenuItem::section('Statistiques');
        yield MenuItem::linkToUrl('Documents analysés', 'fa fa-brain', '#')
            ->setBadge((string) $totalAnalyzed, 'success');
        yield MenuItem::linkToUrl('Assurance', 'fa fa-shield', '#')
            ->setBadge((string) $totalInsurance, 'info');
        yield MenuItem::linkToUrl('Anomalies', 'fa fa-exclamation-triangle', '#')
            ->setBadge((string) $totalAnomalies, 'warning');
    }
}
