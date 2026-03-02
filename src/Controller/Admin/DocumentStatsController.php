<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document')]
#[IsGranted('ROLE_ADMIN')]
class DocumentStatsController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
    ) {
    }

    #[Route('/stats', name: 'admin_document_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $totalDocuments = $this->documentRepository->countAllDocuments();
        $byCategory = $this->documentRepository->countByCategory();
        $byMonth = $this->documentRepository->countByMonth();
        $byUser = $this->documentRepository->countByUser();
        $byInsurance = $this->documentRepository->countByInsurance();

        return $this->render('admin/document/stats.html.twig', [
            'total_documents' => $totalDocuments,
            'by_category' => $byCategory,
            'by_month' => array_reverse($byMonth),
            'by_user' => $byUser,
            'by_insurance' => $byInsurance,
        ]);
    }
}
