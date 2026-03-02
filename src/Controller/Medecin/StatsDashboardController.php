<?php

namespace App\Controller\Medecin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/medecin/stats', name: 'medecin_stats_')]
class StatsDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_MEDECIN', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
            return $this->redirectToRoute('app_frontend_vue');
        }

        $today = new \DateTimeImmutable('today');
        $fromDefault = $today->modify('first day of this month')->format('Y-m-d');
        $toDefault = $today->modify('last day of this month')->format('Y-m-d');
        $yearDefault = $today->format('Y');
        $currentMedecinId = 1;

        if ($user instanceof User) {
            $id = $user->getId();
            if (is_int($id) && $id > 0) {
                $currentMedecinId = $id;
            }
        }

        $medecinIdRaw = trim((string) $request->query->get('medecinId', (string) $currentMedecinId));
        $fromRaw = trim((string) $request->query->get('from', $fromDefault));
        $toRaw = trim((string) $request->query->get('to', $toDefault));
        $yearRaw = trim((string) $request->query->get('year', $yearDefault));
        $includeZeroDaysForMin = $request->query->getBoolean('includeZeroDaysForMin', false);

        return $this->render('frontend/medecin/stats/dashboard.html.twig', [
            'defaultMedecinId' => ctype_digit($medecinIdRaw) ? $medecinIdRaw : (string) $currentMedecinId,
            'defaultFrom' => $this->isValidDate($fromRaw) ? $fromRaw : $fromDefault,
            'defaultTo' => $this->isValidDate($toRaw) ? $toRaw : $toDefault,
            'defaultYear' => ctype_digit($yearRaw) ? $yearRaw : $yearDefault,
            'defaultIncludeZeroDaysForMin' => $includeZeroDaysForMin,
        ]);
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return false !== $date
            && ($errors['warning_count'] ?? 0) === 0
            && ($errors['error_count'] ?? 0) === 0;
    }
}
