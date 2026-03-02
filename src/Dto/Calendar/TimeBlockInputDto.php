<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class TimeBlockInputDto
{
    public function __construct(
        public readonly int $doctorId,
        public readonly string $startDatetime,
        public readonly string $endDatetime,
        public readonly ?string $note = null
    ) {
    }
}
