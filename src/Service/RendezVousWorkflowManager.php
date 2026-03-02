<?php

namespace App\Service;

use App\Entity\RendezVous;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RendezVousWorkflowManager
{
    /**
     * @return string[]
     */
    public function validateBusinessRules(RendezVous $rendezVous): array
    {
        $errors = [];

        $date = $rendezVous->getDateRdv();
        if (null !== $date) {
            $today = new \DateTimeImmutable('today');
            if ($date < $today) {
                $errors[] = 'La date du rendez-vous ne peut pas etre dans le passe.';
            }
        }

        return $errors;
    }

    public function assertValidTransition(string $currentStatus, string $newStatus): void
    {
        if (!in_array($currentStatus, RendezVous::STATUTS, true)) {
            throw new BadRequestHttpException('Statut actuel invalide.');
        }

        if (!in_array($newStatus, RendezVous::STATUTS, true)) {
            throw new BadRequestHttpException('Statut cible invalide.');
        }

        $allowedTransitions = [
            RendezVous::STATUT_EN_COURS => [RendezVous::STATUT_ACCEPTE, RendezVous::STATUT_REFUSE],
            RendezVous::STATUT_ACCEPTE => [],
            RendezVous::STATUT_REFUSE => [],
        ];

        $validTargets = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $validTargets, true)) {
            throw new BadRequestHttpException(sprintf(
                'Transition invalide: %s -> %s.',
                $currentStatus,
                $newStatus
            ));
        }
    }
}
