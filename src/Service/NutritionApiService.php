<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NutritionApiService
{
    public function __construct(private readonly HttpClientInterface $http) {}

    private function sslOptions(): array
    {
        // ✅ DEV ONLY (Windows / SSL)
        return [
            'verify_peer' => false,
            'verify_host' => false,
        ];
    }

    /**
     * ✅ Recherche enrichie: retourne code + label + brand + serving_size + macros (kcal/protein/carbs/fat)
     * -> 1 SEUL appel HTTP (pas de boucle barcode)
     */
    public function searchEnriched(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $url = 'https://world.openfoodfacts.org/cgi/search.pl';

        $resp = $this->http->request('GET', $url, [
            'query' => [
                'search_terms' => $q,
                'search_simple' => 1,
                'action' => 'process',
                'json' => 1,
                'page_size' => max(1, min(50, $limit)),
                // ✅ on demande nutriments pour scorer sans 2e appel
                'fields' => 'code,product_name,brands,serving_size,nutriments',
            ],
            'timeout' => 20,
            ...$this->sslOptions(),
        ]);

        $data = $resp->toArray(false);
        $products = $data['products'] ?? [];

        $items = [];
        foreach ($products as $p) {
            $code = $p['code'] ?? null;
            if (!$code) continue;

            $label = trim((string)($p['product_name'] ?? 'Produit'));
            if ($label === '') $label = 'Produit';

            $brand = trim((string)($p['brands'] ?? ''));
            $serving = trim((string)($p['serving_size'] ?? ''));

            $nut = $p['nutriments'] ?? [];

            // selon dispo : _100g ou _serving
            $kcal = $nut['energy-kcal_100g'] ?? $nut['energy-kcal_serving'] ?? $nut['energy-kcal'] ?? null;
            $protein = $nut['proteins_100g'] ?? $nut['proteins_serving'] ?? null;
            $carbs   = $nut['carbohydrates_100g'] ?? $nut['carbohydrates_serving'] ?? null;
            $fat     = $nut['fat_100g'] ?? $nut['fat_serving'] ?? null;

            $items[] = [
                'code' => (string)$code,
                'label' => $label,
                'brand' => $brand,
                'serving_size' => $serving,
                'calories' => $this->num($kcal),
                'protein' => $this->num($protein),
                'carbs' => $this->num($carbs),
                'fat' => $this->num($fat),
            ];
        }

        return $items;
    }

    /**
     * ✅ Garder ton endpoint barcode (pour "Remplir depuis API")
     * (on le laisse pour ton bouton existant)
     */
    public function getByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') return null;

        $url = 'https://world.openfoodfacts.org/api/v2/product/' . rawurlencode($barcode);

        try {
            $resp = $this->http->request('GET', $url, [
                'query' => [
                    'fields' => 'code,product_name,brands,serving_size,nutriments',
                ],
                'timeout' => 20,
                ...$this->sslOptions(),
            ]);

            $data = $resp->toArray(false);
            if (($data['status'] ?? 0) != 1) return null;

            $product = $data['product'] ?? [];
            $nut = $product['nutriments'] ?? [];

            $kcal    = $nut['energy-kcal_100g'] ?? $nut['energy-kcal_serving'] ?? null;
            $protein = $nut['proteins_100g'] ?? $nut['proteins_serving'] ?? null;
            $carbs   = $nut['carbohydrates_100g'] ?? $nut['carbohydrates_serving'] ?? null;
            $fat     = $nut['fat_100g'] ?? $nut['fat_serving'] ?? null;

            return [
                'barcode' => (string)($product['code'] ?? $barcode),
                'label' => (string)($product['product_name'] ?? 'Produit'),
                'brand' => (string)($product['brands'] ?? ''),
                'serving_size' => (string)($product['serving_size'] ?? ''),
                'calories' => $this->num($kcal),
                'protein' => $this->num($protein),
                'carbs' => $this->num($carbs),
                'fat' => $this->num($fat),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float)$v;
        $v = str_replace(',', '.', (string)$v);
        return is_numeric($v) ? (float)$v : null;
    }
}
