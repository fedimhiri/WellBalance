<?php

namespace App\Service\Risk;

final class RiskReport
{
    /**
     * @param RiskFinding[] $findings
     */
    public function __construct(
        public readonly array $findings,
        public readonly int $score,           // 0..100
        public readonly string $globalLevel,  // INFO/WARN/CRIT
        public readonly array $meta = []
    ) {}
}
