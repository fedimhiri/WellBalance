<?php

namespace App\Controller\Api;

use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stats/medecin', name: 'api_stats_medecin_')]
class StatsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, StatsService $statsService): JsonResponse
    {
        $validation = $this->validateMainStatsQuery($request);
        if (!$validation['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides.',
                'errors' => $validation['errors'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $statsService->getMedecinStats(
            $validation['medecinId'],
            $validation['from'],
            $validation['to'],
            $validation['includeZeroDaysForMin']
        );

        return $this->json([
            'success' => true,
            'message' => 'Statistiques du medecin recuperees.',
            'data' => $data,
        ]);
    }

    #[Route('/mensuel', name: 'mensuel', methods: ['GET'])]
    public function mensuel(Request $request, StatsService $statsService): JsonResponse
    {
        $validation = $this->validateMonthlyQuery($request);
        if (!$validation['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides.',
                'errors' => $validation['errors'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $statsService->getMonthlyStats($validation['medecinId'], $validation['year']);

        return $this->json([
            'success' => true,
            'message' => 'Statistiques mensuelles recuperees.',
            'data' => $data,
        ]);
    }

    /**
     * @return array{
     *   valid: bool,
     *   errors: array<string, string>,
     *   medecinId?: int,
     *   from?: \DateTimeImmutable,
     *   to?: \DateTimeImmutable,
     *   includeZeroDaysForMin?: bool
     * }
     */
    private function validateMainStatsQuery(Request $request): array
    {
        $errors = [];
        $medecinId = $this->parsePositiveInt($request->query->get('medecinId'));
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));
        $includeZeroDaysForMin = $request->query->getBoolean('includeZeroDaysForMin', false);

        if (null === $medecinId) {
            $errors['medecinId'] = 'Le parametre medecinId est obligatoire et doit etre un entier positif.';
        }

        if (null === $from) {
            $errors['from'] = 'Le parametre from est obligatoire au format YYYY-MM-DD.';
        }

        if (null === $to) {
            $errors['to'] = 'Le parametre to est obligatoire au format YYYY-MM-DD.';
        }

        if ($from instanceof \DateTimeImmutable && $to instanceof \DateTimeImmutable && $from > $to) {
            $errors['period'] = 'La date from doit etre inferieure ou egale a la date to.';
        }

        if ([] !== $errors) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'medecinId' => $medecinId,
            'from' => $from,
            'to' => $to,
            'includeZeroDaysForMin' => $includeZeroDaysForMin,
        ];
    }

    /**
     * @return array{
     *   valid: bool,
     *   errors: array<string, string>,
     *   medecinId?: int,
     *   year?: int
     * }
     */
    private function validateMonthlyQuery(Request $request): array
    {
        $errors = [];
        $medecinId = $this->parsePositiveInt($request->query->get('medecinId'));
        $year = $this->parsePositiveInt($request->query->get('year'));

        if (null === $medecinId) {
            $errors['medecinId'] = 'Le parametre medecinId est obligatoire et doit etre un entier positif.';
        }

        if (null === $year) {
            $errors['year'] = 'Le parametre year est obligatoire et doit etre un entier.';
        } elseif ($year < 2000 || $year > 2100) {
            $errors['year'] = 'Le parametre year doit etre compris entre 2000 et 2100.';
        }

        if ([] !== $errors) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'medecinId' => $medecinId,
            'year' => $year,
        ];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            false === $date
            || ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0
        ) {
            return null;
        }

        return $date;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }
}
