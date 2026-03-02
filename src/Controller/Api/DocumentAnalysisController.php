<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Document;
use App\Service\DocumentAnalyzerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class DocumentAnalysisController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentAnalyzerService $analyzerService,
    ) {
    }

    #[Route('/analyze/{id}', name: 'api_document_analyze', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function analyze(Request $request, int $id): JsonResponse
    {
        $document = $this->entityManager->getRepository(Document::class)->find($id);

        if (null === $document) {
            return $this->json([
                'success' => false,
                'error' => 'Document non trouvé',
            ], 404);
        }

        // Check if user owns the document or is admin
        $user = $this->getUser();
        if ($document->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'error' => 'Accès refusé',
            ], 403);
        }

        // If document has existing analysis, return it
        if (null !== $document->getResumeAi() && '' !== $document->getResumeAi()) {
            return $this->json([
                'success' => true,
                'data' => [
                    'type' => $document->getTypeDetecte(),
                    'summary' => $document->getResumeAi(),
                    'mots_cles' => $document->getMotsCles() ?? [],
                ],
            ]);
        }

        // Analyze the PDF on-the-fly
        $pdfPath = $document->getCheminFichier();
        
        if (null === $pdfPath || !file_exists($this->getParameter('kernel.project_dir') . '/public/' . $pdfPath)) {
            return $this->json([
                'success' => false,
                'error' => 'Fichier PDF non trouvé',
            ], 404);
        }

        try {
            // Analyze the PDF
            $analysisResult = $this->analyzerService->analyzePdf($pdfPath);
            
            // Update the document with analysis results
            $document->setTypeDetecte($analysisResult['type_detecte']);
            $document->setResumeAi($analysisResult['resume']);
            $document->setMotsCles($analysisResult['mots_cles']);
            
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'data' => [
                    'type' => $analysisResult['type_detecte'],
                    'summary' => $analysisResult['resume'],
                    'mots_cles' => $analysisResult['mots_cles'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'analyse: ' . $e->getMessage(),
            ], 500);
        }
    }
}
