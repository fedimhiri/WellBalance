<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class DoctorCalendarResponseDto
{
    /**
     * @param CalendarDayDto[] $days
     */
    public function __construct(
        public readonly int $doctorId,
        public readonly string $view,
        public readonly string $from,
        public readonly string $to,
        public readonly array $days
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'doctorId' => $this->doctorId,
            'view' => $this->view,
            'from' => $this->from,
            'to' => $this->to,
            'days' => array_map(
                static fn (CalendarDayDto $day): array => $day->toArray(),
                $this->days
            ),
        ];
    }
}
