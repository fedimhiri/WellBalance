<?php

namespace App\Service;

use App\Repository\RendezVousRepository;

class StatsService
{
    public function __construct(private readonly RendezVousRepository $rendezVousRepository)
    {
    }

    public function getMedecinStats(
        int $medecinId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $includeZeroDaysForMin = false
    ): array {
        $total = $this->fetchTotal($medecinId, $from, $to);
        $byStatusMap = $this->fetchByStatus($medecinId, $from, $to);
        $byDayMap = $this->fetchByDay($medecinId, $from, $to);
        $byTypeMap = $this->fetchByType($medecinId, $from, $to);
        $topHoursMap = $this->fetchTopHours($medecinId, $from, $to, 5);

        $daysCount = ((int) $from->diff($to)->format('%a')) + 1;
        $cancelledCount = (int) (($byStatusMap['ANNULE'] ?? 0) + ($byStatusMap['REFUSE'] ?? 0));
        $confirmedCount = (int) ($byStatusMap['ACCEPTE'] ?? 0);
        $cancelRate = $total > 0 ? round(($cancelledCount / $total) * 100, 2) : 0.0;
        $confirmRate = $total > 0 ? round(($confirmedCount / $total) * 100, 2) : 0.0;
        $avgPerDay = $daysCount > 0 ? round($total / $daysCount, 2) : 0.0;

        $byDayChart = $this->buildByDayChart($from, $to, $byDayMap);
        $busiest = $this->findDayExtreme($byDayChart['labels'], $byDayChart['datasets'][0]['data'], true, true);
        $least = $this->findDayExtreme(
            $byDayChart['labels'],
            $byDayChart['datasets'][0]['data'],
            false,
            $includeZeroDaysForMin
        );

        arsort($byTypeMap);
        $topTypes = [];
        foreach (array_slice($byTypeMap, 0, 3, true) as $typeId => $count) {
            $topTypes[] = [
                'typeRendezVousId' => (int) $typeId,
                'count' => (int) $count,
            ];
        }

        return [
            'meta' => [
                'medecinId' => $medecinId,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'days' => $daysCount,
            ],
            'kpi' => [
                'totalRendezVous' => $total,
                'totalParStatut' => $byStatusMap,
                'tauxAnnulation' => $cancelRate,
                'tauxConfirmation' => $confirmRate,
                'moyenneParJour' => $avgPerDay,
            ],
            'extremes' => [
                'jourPlusCharge' => $busiest,
                'jourMoinsCharge' => $least,
            ],
            'byDay' => $byDayChart,
            'byStatus' => $this->toChartData($byStatusMap, 'Rendez-vous par statut'),
            'byType' => $this->toChartData($byTypeMap, 'Rendez-vous par type'),
            'topTypes' => $topTypes,
            'topHours' => $this->toChartData($topHoursMap, 'Top heures'),
        ];
    }

    public function getMonthlyStats(int $medecinId, int $year): array
    {
        $from = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $to = new \DateTimeImmutable(sprintf('%d-12-31', $year));
        $monthMap = $this->fetchByMonth($medecinId, $from, $to);

        $labels = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];
        for ($month = 1; $month <= 12; ++$month) {
            $data[] = (int) ($monthMap[$month] ?? 0);
        }

        return [
            'meta' => [
                'medecinId' => $medecinId,
                'year' => $year,
            ],
            'byMonth' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => sprintf('Rendez-vous mensuels %d', $year),
                        'data' => $data,
                    ],
                ],
            ],
        ];
    }

    public function getMedecinPdfReportData(
        int $medecinId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $total = $this->fetchTotal($medecinId, $from, $to);
        $byStatus = $this->fetchByStatus($medecinId, $from, $to);
        $byType = $this->fetchByType($medecinId, $from, $to);
        $byDay = $this->fetchByDay($medecinId, $from, $to);

        ksort($byDay);
        ksort($byType);

        $cancelledCount = (int) (($byStatus['ANNULE'] ?? 0) + ($byStatus['REFUSE'] ?? 0));
        $confirmedCount = (int) ($byStatus['ACCEPTE'] ?? 0);
        $cancelRate = $total > 0 ? round(($cancelledCount / $total) * 100, 2) : 0.0;
        $confirmRate = $total > 0 ? round(($confirmedCount / $total) * 100, 2) : 0.0;

        return [
            'medecinId' => $medecinId,
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'byStatus' => $byStatus,
            'byType' => $byType,
            'byDay' => $byDay,
            'tauxAnnulation' => $cancelRate,
            'tauxConfirmation' => $confirmRate,
        ];
    }

    private function fetchTotal(int $medecinId, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    private function fetchByStatus(int $medecinId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('r.statut AS statut, COUNT(r.id) AS total')
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['statut']] = (int) $row['total'];
        }

        ksort($result);

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function fetchByDay(int $medecinId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('r.dateRdv AS dateRdv, COUNT(r.id) AS total')
            ->groupBy('r.dateRdv')
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $dateValue = $row['dateRdv'] ?? null;

            if ($dateValue instanceof \DateTimeInterface) {
                $key = $dateValue->format('Y-m-d');
            } elseif (is_string($dateValue) && '' !== $dateValue) {
                $key = mb_substr($dateValue, 0, 10);
            } else {
                continue;
            }

            $result[$key] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function fetchByType(int $medecinId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('IDENTITY(r.typeRendezVous) AS typeId, COUNT(r.id) AS total')
            ->groupBy('typeId')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            if (!isset($row['typeId'])) {
                continue;
            }

            $result[(int) $row['typeId']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function fetchTopHours(
        int $medecinId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $limit
    ): array {
        $rows = $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('r.heureRdv AS heureRdv, COUNT(r.id) AS total')
            ->groupBy('r.heureRdv')
            ->orderBy('total', 'DESC')
            ->addOrderBy('r.heureRdv', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $heureValue = $row['heureRdv'] ?? null;

            if ($heureValue instanceof \DateTimeInterface) {
                $key = $heureValue->format('H:i');
            } elseif (is_string($heureValue) && '' !== $heureValue) {
                $key = mb_substr($heureValue, 0, 5);
            } else {
                continue;
            }

            $result[$key] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function fetchByMonth(int $medecinId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createPeriodQueryBuilder($medecinId, $from, $to)
            ->select('r.dateRdv AS dateRdv, COUNT(r.id) AS total')
            ->groupBy('r.dateRdv')
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $dateValue = $row['dateRdv'] ?? null;
            $month = 0;

            if ($dateValue instanceof \DateTimeInterface) {
                $month = (int) $dateValue->format('n');
            } elseif (is_string($dateValue) && '' !== $dateValue) {
                $month = (int) mb_substr($dateValue, 5, 2);
            }

            if ($month < 1 || $month > 12) {
                continue;
            }

            $result[$month] = (int) ($result[$month] ?? 0) + (int) $row['total'];
        }

        return $result;
    }

    private function createPeriodQueryBuilder(
        int $medecinId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): \Doctrine\ORM\QueryBuilder {
        return $this->rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecinId')
            ->andWhere('r.dateRdv >= :fromDate')
            ->andWhere('r.dateRdv <= :toDate')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to);
    }

    /**
     * @param array<string|int, int> $map
     *
     * @return array{
     *   labels: array<int, string>,
     *   datasets: array<int, array{label: string, data: array<int, int>}>
     * }
     */
    private function toChartData(array $map, string $label): array
    {
        $labels = [];
        $values = [];

        foreach ($map as $key => $value) {
            $labels[] = (string) $key;
            $values[] = (int) $value;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $values,
                ],
            ],
        ];
    }

    /**
     * @param array<string, int> $byDayMap
     *
     * @return array{
     *   labels: array<int, string>,
     *   datasets: array<int, array{label: string, data: array<int, int>}>
     * }
     */
    private function buildByDayChart(\DateTimeImmutable $from, \DateTimeImmutable $to, array $byDayMap): array
    {
        $labels = [];
        $values = [];
        $cursor = $from;

        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $key;
            $values[] = (int) ($byDayMap[$key] ?? 0);
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Rendez-vous par jour',
                    'data' => $values,
                ],
            ],
        ];
    }

    /**
     * @param string[] $labels
     * @param int[]    $values
     *
     * @return array{date: string, count: int}|null
     */
    private function findDayExtreme(
        array $labels,
        array $values,
        bool $max,
        bool $includeZero
    ): ?array {
        $selectedDate = null;
        $selectedCount = null;

        foreach ($labels as $index => $date) {
            $count = (int) ($values[$index] ?? 0);
            if (!$includeZero && 0 === $count) {
                continue;
            }

            if (null === $selectedCount) {
                $selectedDate = $date;
                $selectedCount = $count;
                continue;
            }

            if ($max && $count > $selectedCount) {
                $selectedDate = $date;
                $selectedCount = $count;
                continue;
            }

            if (!$max && $count < $selectedCount) {
                $selectedDate = $date;
                $selectedCount = $count;
            }
        }

        if (null === $selectedDate || null === $selectedCount) {
            return null;
        }

        return [
            'date' => $selectedDate,
            'count' => $selectedCount,
        ];
    }
}
