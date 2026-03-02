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
                    'type_detecte' => $document->getTypeDetecte(),
                    'resume' => $document->getResumeAi(),
                    'mots_cles' => $document->getMotsCles(),
                ],
            ]);
        }

        // For now, return a message that analysis is not available
        // In production, you would integrate with OCR/AI service here
        return $this->json([
            'success' => true,
            'data' => [
                'type_detecte' => $document->getTypeDetecte() ?? 'Non analysé',
                'resume' => $document->getResumeAi() ?? 'Analyse non disponible. Le document doit être analysé lors de l\'upload.',
                'mots_cles' => $document->getMotsCles() ?? [],
            ],
        ]);
    }
}
