<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents', name: 'admin_document_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentDashboardController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $totalDocuments = $this->documentRepository->countAll();
        $totalAnalyzed = $this->documentRepository->countAnalyzed();
        $totalInsuranceSubmitted = $this->documentRepository->countInsuranceSubmitted();
        $totalAnomalies = $this->documentRepository->countAnomalies();

        return $this->render('admin/document/dashboard.html.twig', [
            'total_documents' => $totalDocuments,
            'total_analyzed' => $totalAnalyzed,
            'total_insurance_submitted' => $totalInsuranceSubmitted,
            'total_anomalies' => $totalAnomalies,
        ]);
    }
}
