<?php

namespace App\Service;

final class ProduitRecoService
{
    public function __construct(private readonly NutritionApiService $api) {}

    public function recommend(string $query, string $objectif, int $limitApi = 20, int $top = 10): array
    {
        $items = $this->api->searchEnriched($query, $limitApi);
        if (!$items) return [];

        foreach ($items as &$it) {
            $it['score'] = $this->scoreProduct($it, $objectif);
            $it['barcode'] = $it['code']; // compat JS
        }
        unset($it);

        usort($items, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($items, 0, $top);
    }

    private function scoreProduct(array $p, string $objectif): float
    {
        $obj = mb_strtolower(trim($objectif));
        $name = mb_strtolower(($p['label'] ?? '') . ' ' . ($p['brand'] ?? ''));

        $kcal = (float)($p['calories'] ?? 0);
        $prot = (float)($p['protein'] ?? 0);
        $carb = (float)($p['carbs'] ?? 0);
        $fat  = (float)($p['fat'] ?? 0);

        $bonusHealthy = $this->containsAny($name, ['thon','poulet','salade','brocoli','yaourt','grec','fromage blanc','oeuf','avoine']) ? 10 : 0;
        $malusJunk = $this->containsAny($name, ['nutella','chips','bonbon','biscuit','soda','cola','chocolat']) ? 15 : 0;

        $lowCalBonus = max(0, 400 - $kcal) / 20;
        $highCalBonus = min(30, $kcal / 20);

        // perte
        if (str_contains($obj, 'perte') || str_contains($obj, 'maigr') || str_contains($obj, 'minc') || str_contains($obj, 'cut')) {
            return (2.2 * $lowCalBonus) + (3.5 * $prot) - (1.8 * $fat) - (0.3 * $carb) + $bonusHealthy - $malusJunk;
        }

        // masse
        if (str_contains($obj, 'prise') || str_contains($obj, 'masse') || str_contains($obj, 'bulk') || str_contains($obj, 'muscle')) {
            $bonusMass = $this->containsAny($name, ['riz','pates','pâtes','banane','lait','avoine','beurre de cacahu']) ? 10 : 0;
            return (2.0 * $highCalBonus) + (2.0 * $carb) + (2.2 * $prot) + (0.3 * $fat) + $bonusMass - (0.5 * $malusJunk);
        }

        // detox
        if (str_contains($obj, 'détox') || str_contains($obj, 'detox')) {
            $bonusDetox = $this->containsAny($name, ['jus','smoothie','the','thé','citron','concombre','soupe','infusion']) ? 10 : 0;
            return (1.5 * $lowCalBonus) - (2.0 * $fat) - (0.6 * $carb) + $bonusDetox - (0.6 * $malusJunk);
        }

        // performance
        if (str_contains($obj, 'performance') || str_contains($obj, 'sport')) {
            $bonusSport = $this->containsAny($name, ['banane','avoine','riz','poulet','yaourt','whey']) ? 10 : 0;
            return (1.0 * $lowCalBonus) + (2.2 * $carb) + (2.0 * $prot) - (1.2 * $fat) + $bonusSport - (0.5 * $malusJunk);
        }

        // maintien
        return (1.4 * $lowCalBonus) + (2.0 * $prot) + (0.8 * $carb) - (1.2 * $fat) + (0.3 * $bonusHealthy) - (0.3 * $malusJunk);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            $n = mb_strtolower(trim($n));
            if ($n !== '' && str_contains($haystack, $n)) return true;
        }
        return false;
    }
}
