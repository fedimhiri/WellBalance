<?php

namespace App\Service;

use App\Entity\AlerteNutrition;
use App\Entity\PlanNutrition;
use App\Entity\SuiviNutrition;
use App\Repository\SuiviNutritionRepository;
use App\Service\Risk\RiskFinding;
use App\Service\Risk\RiskReport;

final class RiskAnalyzerService
{
    public function __construct(
        private readonly SuiviNutritionRepository $suiviRepo,
    ) {}

    /**
     * Version simple (compatible twig actuel): retourne فقط les risques.
     * @return RiskFinding[]
     */
    public function analyze(PlanNutrition $plan): array
    {
        return $this->analyzeReport($plan)->findings;
    }

    /**
     * Version “pro” : risques + score + niveau global.
     */
    public function analyzeReport(PlanNutrition $plan): RiskReport
    {
        $findings = [];

        $objectif = (string) ($plan->getObjectif() ?? '');
        $goalType = $this->inferGoalType($objectif); // 'LOSS'|'GAIN'|'OTHER'

        // données
        $last7Asc  = $this->suiviRepo->lastDays($plan, 7);   // ASC
        $last14Asc = $this->suiviRepo->lastDays($plan, 14);  // ASC
        $last10Desc = $this->suiviRepo->lastN($plan, 10);    // DESC

        // -------------------------
        // R4) Inactivité
        // -------------------------
        $lastDate = $this->suiviRepo->lastSuiviDate($plan);
        if ($lastDate === null) {
            $findings[] = new RiskFinding(
                AlerteNutrition::TYPE_SANS_SUIVI,
                AlerteNutrition::SEV_WARN,
                "Aucun suivi n'a été enregistré pour ce plan.",
                35
            );
        } else {
            $today = new \DateTimeImmutable('today');
            $lastImmutable = \DateTimeImmutable::createFromMutable($lastDate);
            $daysSince = $lastImmutable->diff($today)->days;

            if ($daysSince >= 7) {
                $sev = $daysSince >= 14 ? AlerteNutrition::SEV_CRIT : AlerteNutrition::SEV_WARN;
                $impact = $sev === AlerteNutrition::SEV_CRIT ? 50 : 30;

                $findings[] = new RiskFinding(
                    AlerteNutrition::TYPE_SANS_SUIVI,
                    $sev,
                    "Aucun suivi depuis {$daysSince} jours (dernier : ".$lastDate->format('d/m/Y').").",
                    $impact,
                    ['days_since' => $daysSince, 'last_date' => $lastDate->format('c')]
                );
            }
        }

        // -------------------------
        // R1/R2) Variation de poids sur 7 jours
        // -------------------------
        $weightPoints = $this->extractWeightPoints($last7Asc);

        if (count($weightPoints) >= 2) {
            $first = $weightPoints[0];
            $last  = $weightPoints[count($weightPoints) - 1];

            $fromW = (float) $first->getPoids();
            $toW   = (float) $last->getPoids();
            $delta = $toW - $fromW; // + = gain, - = perte

            // seuil “réaliste” demandé : 2.5kg / 7j
            $threshold = 2.5;

            if ($goalType === 'LOSS' && $delta <= -$threshold) {
                $sev = (abs($delta) >= 3.5) ? AlerteNutrition::SEV_CRIT : AlerteNutrition::SEV_CRIT;
                // tu as demandé CRIT dès 2.5kg => on respecte
                $findings[] = new RiskFinding(
                    AlerteNutrition::TYPE_PERTE_RAPIDE,
                    $sev,
                    sprintf(
                        "Perte de poids trop rapide sur 7 jours : %.1f kg (de %.1f à %.1f).",
                        abs($delta),
                        $fromW,
                        $toW
                    ),
                    55,
                    ['delta' => $delta, 'from' => $fromW, 'to' => $toW]
                );
            }

            if ($goalType === 'GAIN' && $delta >= $threshold) {
                // gain trop rapide (WARN ou CRIT)
                $sev = ($delta >= 3.5) ? AlerteNutrition::SEV_CRIT : AlerteNutrition::SEV_WARN;
                $impact = $sev === AlerteNutrition::SEV_CRIT ? 45 : 30;

                $findings[] = new RiskFinding(
                    'PRISE_POIDS_RAPIDE',
                    $sev,
                    sprintf(
                        "Prise de poids rapide sur 7 jours : +%.1f kg (de %.1f à %.1f).",
                        $delta,
                        $fromW,
                        $toW
                    ),
                    $impact,
                    ['delta' => $delta, 'from' => $fromW, 'to' => $toW]
                );
            }
        }

        // -------------------------
        // R3) Respect faible consécutif
        // -------------------------
        $consecutiveLow = 0;
        foreach ($last10Desc as $s) {
            if ($s->getRespectPourcentage() < 50) {
                $consecutiveLow++;
            } else {
                break;
            }
        }

        if ($consecutiveLow >= 3) {
            $sev = ($consecutiveLow >= 5) ? AlerteNutrition::SEV_CRIT : AlerteNutrition::SEV_WARN;
            $impact = $sev === AlerteNutrition::SEV_CRIT ? 45 : 30;

            $findings[] = new RiskFinding(
                AlerteNutrition::TYPE_RESPECT_FAIBLE,
                $sev,
                "Respect < 50% sur {$consecutiveLow} suivis consécutifs.",
                $impact,
                ['consecutive_low' => $consecutiveLow]
            );
        }

        // -------------------------
        // R5) Humeur / commentaire négatif (keywords)
        // -------------------------
        [$negativeCount, $flagged] = $this->analyzeMoodAndText($last14Asc);

        if ($negativeCount >= 2) {
            $sev = ($negativeCount >= 4) ? AlerteNutrition::SEV_WARN : AlerteNutrition::SEV_INFO;
            $impact = $sev === AlerteNutrition::SEV_WARN ? 20 : 10;

            $findings[] = new RiskFinding(
                'HUMEUR_NEGATIVE',
                $sev,
                "Humeur/commentaires négatifs détectés {$negativeCount} fois sur les 14 derniers jours.",
                $impact,
                ['items' => $flagged]
            );
        }

        // -------------------------
        // Score global + niveau global
        // -------------------------
        $score = 0;
        foreach ($findings as $f) {
            $score += max(0, min(100, (int)$f->scoreImpact));
        }
        $score = min(100, $score);

        $globalLevel = $this->globalSeverityFromFindings($findings);

        return new RiskReport(
            findings: $findings,
            score: $score,
            globalLevel: $globalLevel,
            meta: [
                'goal_type' => $goalType,
                'objectif' => $objectif,
                'findings_count' => count($findings),
            ]
        );
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function inferGoalType(string $objectif): string
    {
        $t = mb_strtolower(trim($objectif));

        // keywords perte
        $loss = ['perte', 'maigr', 'mince', 'detox', 'détox', 'sèche', 'seche', 'cut'];
        foreach ($loss as $k) {
            if ($k !== '' && str_contains($t, $k)) return 'LOSS';
        }

        // keywords prise
        $gain = ['prise', 'masse', 'muscle', 'bulk', 'gain', 'prendre'];
        foreach ($gain as $k) {
            if ($k !== '' && str_contains($t, $k)) return 'GAIN';
        }

        return 'OTHER';
    }

    /**
     * @param SuiviNutrition[] $suivisAsc
     * @return SuiviNutrition[]
     */
    private function extractWeightPoints(array $suivisAsc): array
    {
        $points = [];
        foreach ($suivisAsc as $s) {
            if ($s->getPoids() !== null) {
                $points[] = $s;
            }
        }
        return $points;
    }

    /**
     * @param SuiviNutrition[] $suivis
     * @return array{0:int,1:array}
     */
    private function analyzeMoodAndText(array $suivis): array
    {
        $negativeMoodCount = 0;
        $flagged = [];

        $keywords = [
            'triste','stress','stresse','stressé','stressee','angoisse','déprime','deprime',
            'fatigue','épuis','epuis','mal','douleur','peur','déçu','decu','anxieux','anxiete','anxiété'
        ];

        $badMoods = ['mal', 'stressé', 'stresse', 'stress', 'triste', 'fatigué', 'fatigue', 'angoissé', 'angoisse'];

        foreach ($suivis as $s) {
            $humeur = mb_strtolower((string) $s->getHumeur());
            $comment = mb_strtolower((string) $s->getCommentairePatient());

            $isBadMood = false;
            foreach ($badMoods as $bm) {
                if ($bm !== '' && $humeur !== '' && str_contains($humeur, $bm)) {
                    $isBadMood = true;
                    break;
                }
            }

            $hitKw = null;
            $text = $humeur.' '.$comment;
            foreach ($keywords as $kw) {
                if ($kw !== '' && $text !== '' && str_contains($text, $kw)) {
                    $hitKw = $kw;
                    break;
                }
            }

            if ($isBadMood || $hitKw !== null) {
                $negativeMoodCount++;
                $flagged[] = [
                    'date' => $s->getDateSuivi()?->format('d/m/Y'),
                    'humeur' => $s->getHumeur(),
                    'commentaire' => $s->getCommentairePatient(),
                    'keyword' => $hitKw,
                ];
            }
        }

        return [$negativeMoodCount, $flagged];
    }

    /**
     * @param RiskFinding[] $findings
     */
    private function globalSeverityFromFindings(array $findings): string
    {
        $level = AlerteNutrition::SEV_INFO;

        foreach ($findings as $f) {
            if ($f->severity === AlerteNutrition::SEV_CRIT) return AlerteNutrition::SEV_CRIT;
            if ($f->severity === AlerteNutrition::SEV_WARN) $level = AlerteNutrition::SEV_WARN;
        }

        return $level;
    }
}
        