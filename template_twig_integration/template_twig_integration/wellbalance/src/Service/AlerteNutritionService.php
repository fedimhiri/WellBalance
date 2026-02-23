<?php

namespace App\Service;

use App\Entity\AlerteNutrition;
use App\Entity\PlanNutrition;
use App\Repository\AlerteNutritionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AlerteNutritionService
{
    public function __construct(
        private readonly RiskAnalyzerService $riskAnalyzer,
        private readonly AlerteNutritionRepository $alerteRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Recalcule + persiste les alertes d’un plan.
     * - crée si pas d’alerte ouverte
     * - met à jour si alerte ouverte existe (message / severity)
     * - optionnel: resolve les alertes qui ne sont plus présentes
     */
    public function regenerateForPlan(PlanNutrition $plan, bool $resolveMissing = true): void
    {
        $patient = $plan->getUser();
        $nutri = $plan->getNutritionniste();

        $report = $this->riskAnalyzer->analyzeReport($plan);

        // On persiste seulement les types “DB” (ceux qui ont du sens en alertes persistées)
        $persistableTypes = [
            AlerteNutrition::TYPE_SANS_SUIVI,
            AlerteNutrition::TYPE_RESPECT_FAIBLE,
            AlerteNutrition::TYPE_PERTE_RAPIDE,
            'PRISE_POIDS_RAPIDE',
            'HUMEUR_NEGATIVE',
        ];

        $seenTypes = [];

        foreach ($report->findings as $f) {
            if (!in_array($f->type, $persistableTypes, true)) {
                continue;
            }

            $seenTypes[] = $f->type;

            $open = $this->alerteRepo->findOpen($f->type, $plan, $patient);

            if ($open) {
                // ✅ update (si changement)
                $changed = false;

                if ($open->getSeverity() !== $f->severity) {
                    $open->setSeverity($f->severity);
                    $changed = true;
                }

                if ($open->getMessage() !== $f->message) {
                    $open->setMessage($f->message);
                    $changed = true;
                }

                // si l’alerte était lue, on peut la remettre non lue si elle change
                if ($changed) {
                    $open->setIsRead(false);
                }
            } else {
                // ✅ create
                $a = (new AlerteNutrition())
                    ->setType($f->type)
                    ->setSeverity($f->severity)
                    ->setMessage($f->message)
                    ->setPatient($patient)
                    ->setPlanNutrition($plan)
                    ->setNutritionniste($nutri);

                $this->em->persist($a);
            }
        }

        // ✅ option : résoudre les alertes qui ne sont plus détectées
        if ($resolveMissing) {
            $openAlerts = $this->alerteRepo->findBy(
                ['planNutrition' => $plan, 'patient' => $patient, 'resolvedAt' => null],
                ['createdAt' => 'DESC']
            );

            foreach ($openAlerts as $a) {
                if (in_array($a->getType(), $persistableTypes, true) && !in_array($a->getType(), $seenTypes, true)) {
                    $a->resolve();
                }
            }
        }

        $this->em->flush();
    }
}
