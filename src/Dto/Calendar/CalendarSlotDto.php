<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final class CalendarSlotDto
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly string $state,
        public readonly ?string $reason = null,
        public readonly int $pendingCount = 0
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'state' => $this->state,
            'reason' => $this->reason,
            'pendingCount' => $this->pendingCount,
        ];
    }
}
