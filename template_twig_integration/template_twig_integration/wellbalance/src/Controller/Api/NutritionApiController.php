<?php

namespace App\Controller\Api;

use App\Service\NutritionApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/nutrition')]
final class NutritionApiController extends AbstractController
{
    #[Route('/search', name: 'api_nutrition_search', methods: ['GET'])]
    public function search(Request $request, NutritionApiService $api): JsonResponse
    {
        $q = (string) $request->query->get('q', '');

        // ✅ IMPORTANT : version enrichie (macros) -> stable & rapide
        $items = $api->searchEnriched($q, 20);

        return $this->json([
            'q' => $q,
            'count' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/barcode/{code}', name: 'api_nutrition_barcode', methods: ['GET'])]
    public function barcode(string $code, NutritionApiService $api): JsonResponse
    {
        $item = $api->getByBarcode($code);

        return $this->json([
            'found' => $item !== null,
            'item' => $item,
        ]);
    }
}
