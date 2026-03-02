<?php

namespace App\Service;

use App\Entity\RendezVous;
use App\Repository\RendezVousRepository;

class RendezVousConflictManager
{
    private const START_HOUR = '08:00';
    private const END_HOUR = '18:00';

    public function __construct(
        private readonly RendezVousRepository $repository,
        private readonly int $slotStepMinutes = 15
    ) {
    }

    /**
     * @return array{isPast: bool, hasConflict: bool, suggestions: string[]}
     */
    public function analyze(RendezVous $rendezVous, ?int $excludeId = null): array
    {
        if ($this->isPastDateTime($rendezVous)) {
            return [
                'isPast' => true,
                'hasConflict' => false,
                'suggestions' => [],
            ];
        }

        $medecin = $rendezVous->getMedecin();
        $date = $rendezVous->getDateRdv();
        $time = $rendezVous->getHeureRdv();

        if (null === $medecin || null === $medecin->getId() || null === $date || null === $time) {
            return [
                'isPast' => false,
                'hasConflict' => false,
                'suggestions' => [],
            ];
        }

        $hasConflict = $this->repository->hasActiveConflictForMedecin(
            $medecin->getId(),
            $date,
            $time,
            $excludeId
        );

        return [
            'isPast' => false,
            'hasConflict' => $hasConflict,
            'suggestions' => $hasConflict
                ? $this->findNextAvailableSlots($medecin->getId(), $date, $time, 5, $excludeId)
                : [],
        ];
    }

    private function isPastDateTime(RendezVous $rendezVous): bool
    {
        $date = $rendezVous->getDateRdv();
        $time = $rendezVous->getHeureRdv();

        if (null === $date || null === $time) {
            return false;
        }

        $dateTime = new \DateTimeImmutable(sprintf(
            '%s %s',
            $date->format('Y-m-d'),
            $time->format('H:i')
        ));

        return $dateTime < new \DateTimeImmutable();
    }

    /**
     * @return string[]
     */
    private function findNextAvailableSlots(
        int $medecinId,
        \DateTimeImmutable $requestedDate,
        \DateTimeImmutable $requestedTime,
        int $limit = 5,
        ?int $excludeId = null
    ): array {
        $stepMinutes = max(1, $this->slotStepMinutes);
        $suggestions = [];
        $dayOffset = 0;
        $cursor = $this->roundUpToStep(
            new \DateTimeImmutable(sprintf(
                '%s %s',
                $requestedDate->format('Y-m-d'),
                $requestedTime->format('H:i')
            )),
            $stepMinutes
        );

        while (count($suggestions) < $limit && $dayOffset < 30) {
            $currentDate = $requestedDate->modify(sprintf('+%d day', $dayOffset));
            $dayStart = new \DateTimeImmutable($currentDate->format('Y-m-d').' '.self::START_HOUR);
            $dayEnd = new \DateTimeImmutable($currentDate->format('Y-m-d').' '.self::END_HOUR);

            $slot = $dayOffset === 0 && $cursor > $dayStart ? $cursor : $dayStart;
            $slot = $this->roundUpToStep($slot, $stepMinutes);

            $occupied = array_fill_keys(
                $this->repository->findOccupiedTimesForMedecinOnDate($medecinId, $currentDate, $excludeId),
                true
            );

            while ($slot < $dayEnd && count($suggestions) < $limit) {
                $timeKey = $slot->format('H:i');
                if (!isset($occupied[$timeKey])) {
                    $suggestions[] = $timeKey;
                }

                $slot = $slot->modify(sprintf('+%d minutes', $stepMinutes));
            }

            ++$dayOffset;
        }

        return $suggestions;
    }

    private function roundUpToStep(\DateTimeImmutable $value, int $stepMinutes): \DateTimeImmutable
    {
        $minutes = (int) $value->format('i');
        $remainder = $minutes % $stepMinutes;

        if (0 === $remainder) {
            return $value->setTime((int) $value->format('H'), $minutes, 0);
        }

        $minutesToAdd = $stepMinutes - $remainder;
        $rounded = $value->modify(sprintf('+%d minutes', $minutesToAdd));

        return $rounded->setTime((int) $rounded->format('H'), (int) $rounded->format('i'), 0);
    }
}
