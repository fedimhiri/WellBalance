<?php

namespace App\Service;

use App\Repository\RendezVousRepository;

class DoctorAvailabilityService
{
    private const DEFAULT_SLOT_MINUTES = 15;
    private const WORKING_HOUR_START = '08:00';
    private const WORKING_HOUR_END = '18:00';

    private readonly \DateTimeZone $timezone;

    public function __construct(
        private readonly RendezVousRepository $rendezVousRepository
    ) {
        $this->timezone = new \DateTimeZone('Africa/Tunis');
    }

    /**
     * @return array{
     *   timezone: string,
     *   slotMinutes: int,
     *   workingHours: array{start: string, end: string},
     *   days: array<int, array{date: string, isAvailable: bool, availableSlots: string[]}>
     * }
     */
    public function getAvailability(int $doctorId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromDate = $from->setTimezone($this->timezone)->setTime(0, 0, 0);
        $toDate = $to->setTimezone($this->timezone)->setTime(0, 0, 0);
        $now = new \DateTimeImmutable('now', $this->timezone);
        $today = $now->setTime(0, 0, 0);

        $slotMinutes = self::DEFAULT_SLOT_MINUTES;
        $dailySlots = $this->generateDailySlots($slotMinutes);
        $occupiedRows = $this->rendezVousRepository->findOccupiedSlotsForMedecinBetween(
            $doctorId,
            $fromDate,
            $toDate
        );

        $occupiedByDate = [];
        foreach ($occupiedRows as $row) {
            $occupiedByDate[$row['date']][$row['time']] = true;
        }

        $days = [];
        $cursor = $fromDate;

        while ($cursor <= $toDate) {
            $dateKey = $cursor->format('Y-m-d');

            if ($cursor < $today) {
                $days[] = [
                    'date' => $dateKey,
                    'isAvailable' => false,
                    'availableSlots' => [],
                ];
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $availableSlots = $dailySlots;
            if ($cursor->format('Y-m-d') === $today->format('Y-m-d')) {
                $currentTime = $now->format('H:i');
                $availableSlots = array_values(array_filter(
                    $availableSlots,
                    static fn (string $slot): bool => $slot > $currentTime
                ));
            }

            $occupied = $occupiedByDate[$dateKey] ?? [];
            $availableSlots = array_values(array_filter(
                $availableSlots,
                static fn (string $slot): bool => !isset($occupied[$slot])
            ));

            $days[] = [
                'date' => $dateKey,
                'isAvailable' => count($availableSlots) > 0,
                'availableSlots' => $availableSlots,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'timezone' => $this->timezone->getName(),
            'slotMinutes' => $slotMinutes,
            'workingHours' => [
                'start' => self::WORKING_HOUR_START,
                'end' => self::WORKING_HOUR_END,
            ],
            'days' => $days,
        ];
    }

    /**
     * @return string[]
     */
    private function generateDailySlots(int $stepMinutes): array
    {
        $day = new \DateTimeImmutable('today', $this->timezone);
        $start = new \DateTimeImmutable($day->format('Y-m-d').' '.self::WORKING_HOUR_START, $this->timezone);
        $end = new \DateTimeImmutable($day->format('Y-m-d').' '.self::WORKING_HOUR_END, $this->timezone);

        $slots = [];
        $cursor = $start;
        while ($cursor < $end) {
            $slots[] = $cursor->format('H:i');
            $cursor = $cursor->modify(sprintf('+%d minutes', $stepMinutes));
        }

        return $slots;
    }
}
