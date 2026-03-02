<?php

namespace App\Controller\Admin\Nutrition;

use App\Entity\PlanNutrition;
use App\Repository\SuiviNutritionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/nutrition/suivis')]
#[IsGranted('ROLE_NUTRITIONNISTE')]
class SuiviNutritionController extends AbstractController
{
    #[Route('/plan/{id}', name: 'admin_suivi_index', methods: ['GET'])]
    public function index(PlanNutrition $plan, SuiviNutritionRepository $repo): Response
    {
        // ✅ Sécurité métier : le nutritionniste voit seulement ses plans
        if ($plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $suivis = $repo->findBy(['planNutrition' => $plan], ['dateSuivi' => 'DESC']);

        return $this->render('admin/nutrition/suivi/index.html.twig', [
            'plan' => $plan,
            'suivis' => $suivis,
        ]);
    }
}
