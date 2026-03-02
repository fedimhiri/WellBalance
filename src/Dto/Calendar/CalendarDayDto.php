<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class CalendarDayDto
{
    /**
     * @param CalendarSlotDto[] $slots
     */
    public function __construct(
        public readonly string $date,
        public readonly bool $isAvailable,
        public readonly array $slots,
        public readonly int $totalSlots,
        public readonly int $availableSlots,
        public readonly int $busySlots,
        public readonly int $unavailableSlots,
        public readonly string $dayStatus,
        public readonly int $pendingCount
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'isAvailable' => $this->isAvailable,
            'dayStatus' => $this->dayStatus,
            'totalSlots' => $this->totalSlots,
            'availableSlots' => $this->availableSlots,
            'busySlots' => $this->busySlots,
            'unavailableSlots' => $this->unavailableSlots,
            'summary' => [
                'totalSlots' => $this->totalSlots,
                'availableSlots' => $this->availableSlots,
                'busySlots' => $this->busySlots,
                'unavailableSlots' => $this->unavailableSlots,
                'pending' => $this->pendingCount,
            ],
            'slots' => array_map(
                static fn (CalendarSlotDto $slot): array => $slot->toArray(),
                $this->slots
            ),
        ];
    }
}
