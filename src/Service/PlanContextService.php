<?php

namespace App\Service;

use App\Repository\PlanNutritionRepository;
use App\Repository\RepasRepository;

final class PlanContextService
{
    public function __construct(
        private readonly PlanNutritionRepository $planRepo,
        private readonly RepasRepository $repasRepo,
    ) {}

    public function buildForUser(int $userId): string
    {
        $plan = $this->planRepo->findActivePlanForUserId($userId);

        if (!$plan) {
            return "Aucun plan actif pour ce patient. Donne des conseils généraux et propose de créer/activer un plan.";
        }

        $lastMeals = $this->repasRepo->findLastForPlan((int)$plan->getId(), 5);

        $mealsTxt = "";
        foreach ($lastMeals as $r) {
            // adapte si tes getters ont d'autres noms
            $mealsTxt .= "- ".$r->getTypeRepas()
                ." | ".$r->getCalories()." kcal"
                ." | P".$r->getProteines()
                ." C".$r->getGlucides()
                ." L".$r->getLipides()
                ." | ".$r->getDateRepas()?->format('Y-m-d H:i')
                ."\n";
        }

        return sprintf(
            "Objectif: %s\nPériode: %s -> %s\nDescription: %s\nDerniers repas:\n%s",
            (string)$plan->getObjectif(),
            $plan->getDateDebut()?->format('Y-m-d'),
            $plan->getDateFin()?->format('Y-m-d'),
            substr((string)$plan->getDescription(), 0, 700),
            $mealsTxt ?: "- (aucun repas enregistré)\n"
        );
    }
}