<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NutritionLookupService
{
    private const OFF_SEARCH_URL = 'https://world.openfoodfacts.org/cgi/search.pl';
    private const OFF_PRODUCT_URL = 'https://world.openfoodfacts.org/api/v2/product/';

    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Recherche par texte (nom aliment/produit).
     * Retourne une liste courte de résultats.
     */
    public function searchProducts(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(20, $limit));

        try {
            $resp = $this->http->request('GET', self::OFF_SEARCH_URL, [
                'query' => [
                    'search_terms' => $query,
                    'search_simple' => 1,
                    'action' => 'process',
                    'json' => 1,
                    'page_size' => $limit,
                    // champs utiles seulement
                    'fields' => implode(',', [
                        'code',
                        'product_name',
                        'brands',
                        'image_small_url',
                        'nutriments',
                        'serving_size',
                    ]),
                ],
                'headers' => [
                    // User-Agent recommandé pour APIs publiques
                    'User-Agent' => 'WellBalance-NutritionModule/1.0 (Symfony)',
                ],
                'timeout' => 10,
            ]);

            $data = $resp->toArray(false);
        } catch (TransportExceptionInterface $e) {
            return [];
        }

        $products = $data['products'] ?? [];
        $out = [];

        foreach ($products as $p) {
            $norm = $this->normalizeProduct($p);
            if ($norm !== null) {
                $out[] = $norm;
            }
        }

        return $out;
    }

    /**
     * Recherche directe par code-barres.
     */
    public function getByBarcode(string $barcode): ?array
    {
        $barcode = preg_replace('/\D+/', '', $barcode ?? '');
        if ($barcode === '') {
            return null;
        }

        try {
            $resp = $this->http->request('GET', self::OFF_PRODUCT_URL.$barcode, [
                'query' => [
                    'fields' => implode(',', [
                        'code',
                        'product_name',
                        'brands',
                        'image_small_url',
                        'nutriments',
                        'serving_size',
                    ]),
                ],
                'headers' => [
                    'User-Agent' => 'WellBalance-NutritionModule/1.0 (Symfony)',
                ],
                'timeout' => 10,
            ]);

            $data = $resp->toArray(false);
        } catch (TransportExceptionInterface $e) {
            return null;
        }

        if (($data['status'] ?? 0) !== 1) {
            return null;
        }

        $product = $data['product'] ?? null;
        if (!is_array($product)) {
            return null;
        }

        return $this->normalizeProduct($product);
    }

    /**
     * Normalise un produit OFF en format simple.
     * On lit les nutriments /100g si disponibles.
     */
    private function normalizeProduct(array $p): ?array
    {
        $nutr = $p['nutriments'] ?? [];
        if (!is_array($nutr)) {
            $nutr = [];
        }

        $name = trim((string)($p['product_name'] ?? ''));
        $brands = trim((string)($p['brands'] ?? ''));
        $code = (string)($p['code'] ?? '');
        $serving = trim((string)($p['serving_size'] ?? ''));

        // OFF: nutriments souvent en *_100g
        $cal = $this->num($nutr['energy-kcal_100g'] ?? $nutr['energy-kcal'] ?? null);
        $protein = $this->num($nutr['proteins_100g'] ?? $nutr['proteins'] ?? null);
        $carbs = $this->num($nutr['carbohydrates_100g'] ?? $nutr['carbohydrates'] ?? null);
        $fat = $this->num($nutr['fat_100g'] ?? $nutr['fat'] ?? null);

        // Si vraiment rien => ignorer
        if ($cal === null && $protein === null && $carbs === null && $fat === null) {
            // On garde quand même si on a un nom ? (au choix)
            if ($name === '' && $brands === '') {
                return null;
            }
        }

        $label = trim($name.' '.($brands !== '' ? "($brands)" : ''));

        return [
            'barcode' => $code !== '' ? $code : null,
            'label' => $label !== '' ? $label : ($code !== '' ? "Produit $code" : 'Produit'),
            'product_name' => $name !== '' ? $name : null,
            'brands' => $brands !== '' ? $brands : null,
            'image' => $p['image_small_url'] ?? null,

            // valeurs /100g (généralement)
            'per' => '100g',
            'serving_size' => $serving !== '' ? $serving : null,

            'calories' => $cal,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
        ];
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        return null;
    }
}
