<?php

namespace App\Controller;

use App\Entity\PlanNutrition;
use App\Service\ProduitRecoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_NUTRITIONNISTE')]
final class ProduitRecoController extends AbstractController
{
    // ✅ IMPORTANT : /admin/... (pour matcher ton Twig)
    #[Route('/admin/nutrition/plans/{id}/produits/reco', name: 'admin_produits_reco', methods: ['GET'])]
    public function reco(PlanNutrition $plan, Request $request, ProduitRecoService $reco): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $objectif = (string) ($plan->getObjectif() ?? '');

        // ✅ reco triée selon objectif (pas de boucle barcode si tu as la version "searchEnriched")
        $best = $reco->recommend($query, $objectif, limitApi: 20, top: 10);

        return $this->json([
            'objectif' => $objectif,
            'q' => $query,
            'items' => $best,
        ]);
    }
}
