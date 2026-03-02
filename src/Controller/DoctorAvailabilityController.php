<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\DoctorAvailabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DoctorAvailabilityController extends AbstractController
{
    #[Route('/doctors/{doctorId}/availability', name: 'doctor_availability', methods: ['GET'])]
    public function index(
        int $doctorId,
        Request $request,
        UserRepository $userRepository,
        DoctorAvailabilityService $availabilityService
    ): JsonResponse {
        $fromRaw = (string) $request->query->get('from', '');
        $toRaw = (string) $request->query->get('to', '');

        $from = $this->parseDate($fromRaw);
        $to = $this->parseDate($toRaw);

        if (null === $from || null === $to) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides',
                'errors' => [
                    'from' => 'Format attendu YYYY-MM-DD.',
                    'to' => 'Format attendu YYYY-MM-DD.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($from > $to) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides',
                'errors' => [
                    'range' => 'La date from doit etre inferieure ou egale a to.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($to->diff($from)->days > 93) {
            return $this->json([
                'success' => false,
                'message' => 'Plage trop grande',
                'errors' => [
                    'range' => 'Maximum 93 jours par requete.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $doctor = $userRepository->find($doctorId);
        if (null === $doctor || !in_array('ROLE_MEDECIN', $doctor->getRoles(), true)) {
            return $this->json([
                'success' => false,
                'message' => 'Medecin introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $availabilityService->getAvailability($doctorId, $from, $to),
        ]);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $date) {
            return null;
        }

        return $date->setTime(0, 0, 0);
    }
}
