<?php

namespace App\Controller\Admin\Nutrition;

use App\Entity\PlanNutrition;
use App\Service\ProduitRecoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/nutrition/plans')]
#[IsGranted('ROLE_NUTRITIONNISTE')]
final class ProduitRecoController extends AbstractController
{
    #[Route('/{id}/produits/reco', name: 'admin_produits_reco', methods: ['GET'])]
    public function reco(PlanNutrition $plan, Request $request, ProduitRecoService $reco): JsonResponse
    {
        // ✅ sécurité métier (comme ton RepasController)
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $q = (string)$request->query->get('q', '');
        $objectif = (string)($plan->getObjectif() ?? '');

        $items = $reco->recommend($q, $objectif, limitApi: 20, top: 10);

        return $this->json([
            'objectif' => $objectif,
            'q' => $q,
            'items' => $items,
        ]);
    }
}
