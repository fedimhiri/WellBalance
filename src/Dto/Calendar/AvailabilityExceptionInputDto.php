<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class AvailabilityExceptionInputDto
{
    public function __construct(
        public readonly int $doctorId,
        public readonly string $date,
        public readonly string $type,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null
    ) {
    }
}
