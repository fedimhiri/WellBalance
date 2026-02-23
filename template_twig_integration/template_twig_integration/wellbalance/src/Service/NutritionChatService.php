<?php

namespace App\Service;

use App\Repository\RepasRepository;

final class NutritionChatService
{
    public function __construct(
        private readonly PlanContextService $context,
        private readonly RepasRepository $repasRepo,
    ) {}

    public function ask(int $userId, string $message, ?string $previousResponseId = null): array
    {
        $ctx = $this->context->buildForUser($userId);
        $msg = mb_strtolower(trim($message));

        // Détecter objectif (simple) depuis le contexte plan
        $objectif = $this->detectObjectif($ctx);

        // 1) Calories aujourd’hui
        if ($this->containsAny($msg, ['calorie', 'kcal', "aujourd'hui", 'aujourd hui', 'today'])) {
            $total = $this->repasRepo->getTodayTotalCaloriesForUser($userId);

            $target = $this->targetCalories($objectif);
            $diff = $target - $total;

            $advice = $diff >= 0
                ? "✅ Tu es dans ton objectif. Il te reste environ {$diff} kcal pour aujourd’hui."
                : "⚠️ Tu as dépassé ton objectif d’environ ".abs($diff)." kcal aujourd’hui.";

            return [
                "answer" =>
                    "🔥 Total calories aujourd’hui : **{$total} kcal**\n" .
                    "🎯 Objectif estimé ({$objectif}) : **{$target} kcal**\n\n" .
                    $advice . "\n\n" .
                    "Si tu veux, dis-moi ce que tu as mangé ce soir et je te propose une correction.",
                "response_id" => null,
            ];
        }

        // 2) Fast-food
        if ($this->containsAny($msg, ['pizza', 'burger', 'frites', 'fast food', 'tacos', 'kfc', 'mcdo'])) {
            return [
                "answer" =>
                    "🍕 Oui, possible **sans casser ton plan** :\n\n" .
                    "- Portion : **2 parts** (ou 1 mini pizza)\n" .
                    "- Fréquence : **1 fois / semaine max**\n" .
                    "- Astuce : prends **salade + eau**, évite soda/frites\n\n" .
                    "✅ Alternative plus saine : pizza maison (pâte complète) + légumes + poulet/thon.",
                "response_id" => null,
            ];
        }

        // 3) Proposer un repas (petit dej / dej / diner)
        if ($this->containsAny($msg, ['propose', 'suggestion', 'idée', 'idee', 'menu', 'repas', 'petit déjeuner', 'petit dejeuner', 'déjeuner', 'dejeuner', 'dîner', 'diner'])) {
            $type = $this->detectMealType($msg);

            return [
                "answer" => $this->mealSuggestion($objectif, $type),
                "response_id" => null,
            ];
        }

        // 4) Si question vague
        return [
            "answer" =>
                "Je peux t’aider selon ton plan (**{$objectif}**) ✅\n\n" .
                "Dis-moi juste :\n" .
                "1) Tu veux un **petit déjeuner / déjeuner / dîner** ?\n" .
                "2) Ou tu veux savoir si un aliment est OK (ex: pizza, pain, sucre) ?",
            "response_id" => null,
        ];
    }

    private function detectObjectif(string $ctx): string
    {
        $c = mb_strtolower($ctx);
        if (str_contains($c, 'perte')) return 'perte de poids';
        if (str_contains($c, 'prise')) return 'prise de masse';
        if (str_contains($c, 'maintien')) return 'maintien';
        return 'équilibre';
    }

    private function targetCalories(string $objectif): int
    {
        return match ($objectif) {
            'perte de poids' => 1700,
            'prise de masse' => 2600,
            'maintien' => 2200,
            default => 2000,
        };
    }

    private function detectMealType(string $msg): string
    {
        if (str_contains($msg, 'petit')) return 'petit déjeuner';
        if (str_contains($msg, 'deje')) return 'déjeuner';
        if (str_contains($msg, 'din')) return 'dîner';
        return 'repas';
    }

    private function mealSuggestion(string $objectif, string $type): string
    {
        // Suggestions simples “métier”
        if ($objectif === 'perte de poids') {
            return "🥗 **Suggestion {$type} (perte de poids)**\n\n"
                . "- Protéines : 150g poulet/thon/œufs\n"
                . "- Légumes : grand bol (salade, brocoli, haricots)\n"
                . "- Glucides : 1/2 verre riz complet OU 1 tranche pain complet\n"
                . "- Lipides : 1 c.à.s huile d’olive\n\n"
                . "💡 Astuce : évite les boissons sucrées.";
        }

        if ($objectif === 'prise de masse') {
            return "💪 **Suggestion {$type} (prise de masse)**\n\n"
                . "- Protéines : 180–200g poulet/viande maigre/poisson\n"
                . "- Glucides : 1 verre riz/pâtes + 1 fruit\n"
                . "- Lipides : 20g noix/amandes\n"
                . "- Bonus : 1 yaourt grec\n\n"
                . "💡 Objectif : manger assez + qualité.";
        }

        return "✅ **Suggestion {$type} (équilibre)**\n\n"
            . "- Protéines : 150g (poulet/poisson/œufs)\n"
            . "- Légumes : grand bol\n"
            . "- Glucides : portion moyenne (riz/pain/pâtes)\n"
            . "- Lipides : 10–15g (huile d’olive/noix)\n\n"
            . "💡 Boire de l’eau et limiter le sucre.";
    }

    private function containsAny(string $msg, array $words): bool
    {
        foreach ($words as $w) {
            if (str_contains($msg, $w)) return true;
        }
        return false;
    }
}