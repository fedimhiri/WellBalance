<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class RecurringAvailabilityInputDto
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $doctorId,
        public readonly int $weekday,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly int $slotMinutes
    ) {
    }
}
