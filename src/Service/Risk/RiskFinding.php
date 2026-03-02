<?php

namespace App\Service\Risk;

final class RiskFinding
{
    public function __construct(
        public readonly string $type,
        public readonly string $severity,  // INFO/WARN/CRIT
        public readonly string $message,
        public readonly int $scoreImpact = 0, // 0..100 (impact sur score global)
        public readonly array $meta = []
    ) {}
}
