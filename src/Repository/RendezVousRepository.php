<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    /**
     * Statuses that do not block a slot.
     *
     * @var string[]
     */
    private const NON_BLOCKING_STATUSES = ['REFUSE', 'ANNULE'];
    /**
     * @var string[]
     */
    private const NON_ACTIVE_AVAILABILITY_STATUSES = ['ANNULE', 'REFUSE', 'NO_SHOW', 'TERMINE'];

    /**
     * @var array<string, string>
     */
    private const ADVANCED_SORT_WHITELIST = [
        'dateRdv' => 'r.dateRdv',
        'statut' => 'r.statut',
        'type' => 't.nomType',
        'patient' => 'p.username',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * @return RendezVous[]
     */
    public function findForPatient(User $patient): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('r.dateRdv', 'ASC')
            ->addOrderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RendezVous[]
     */
    public function searchForPatientList(
        User $patient,
        ?string $search = null,
        string $sort = 'date',
        string $direction = 'asc',
        ?string $statut = null,
        ?int $typeId = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.medecin', 'm')
            ->leftJoin('r.typeRendezVous', 't')
            ->addSelect('m', 't')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patient);

        if (null !== $search && '' !== trim($search)) {
            $term = '%'.mb_strtolower(trim($search)).'%';
            $qb->andWhere(
                'LOWER(r.titre) LIKE :term
                OR LOWER(COALESCE(r.description, \'\')) LIKE :term
                OR LOWER(COALESCE(m.username, \'\')) LIKE :term
                OR LOWER(COALESCE(m.email, \'\')) LIKE :term
                OR LOWER(COALESCE(t.nomType, \'\')) LIKE :term
                OR LOWER(r.statut) LIKE :term'
            )->setParameter('term', $term);
        }

        if (null !== $statut && in_array($statut, RendezVous::STATUTS, true)) {
            $qb->andWhere('r.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if (null !== $typeId && $typeId > 0) {
            $qb->andWhere('t.id = :typeId')
                ->setParameter('typeId', $typeId);
        }

        $allowedSorts = [
            'id' => 'r.id',
            'titre' => 'r.titre',
            'date' => 'r.dateRdv',
            'heure' => 'r.heureRdv',
            'medecin' => 'm.username',
            'statut' => 'r.statut',
        ];

        $sortField = $allowedSorts[$sort] ?? $allowedSorts['date'];
        $sortDirection = 'desc' === mb_strtolower($direction) ? 'DESC' : 'ASC';

        $qb->orderBy($sortField, $sortDirection)
            ->addOrderBy('r.dateRdv', 'ASC')
            ->addOrderBy('r.heureRdv', 'ASC')
            ->addOrderBy('r.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return RendezVous[]
     */
    public function findForMedecin(User $medecin): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.dateRdv', 'ASC')
            ->addOrderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RendezVous[]
     */
    public function searchForMedecinList(
        ?string $search = null,
        string $sort = 'date',
        string $direction = 'asc',
        ?string $statut = null,
        ?int $medecinId = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->leftJoin('r.typeRendezVous', 't')
            ->addSelect('p', 't');

        if (null !== $medecinId && $medecinId > 0) {
            $qb->andWhere('r.medecin = :medecinId')
                ->setParameter('medecinId', $medecinId);
        }

        if (null !== $search && '' !== trim($search)) {
            $term = '%'.mb_strtolower(trim($search)).'%';
            $qb->andWhere(
                'LOWER(r.titre) LIKE :term
                OR LOWER(COALESCE(r.description, \'\')) LIKE :term
                OR LOWER(COALESCE(p.username, \'\')) LIKE :term
                OR LOWER(COALESCE(p.email, \'\')) LIKE :term
                OR LOWER(COALESCE(t.nomType, \'\')) LIKE :term
                OR LOWER(r.statut) LIKE :term'
            )->setParameter('term', $term);
        }

        if (null !== $statut && in_array($statut, RendezVous::STATUTS, true)) {
            $qb->andWhere('r.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $allowedSorts = [
            'id' => 'r.id',
            'date' => 'r.dateRdv',
            'heure' => 'r.heureRdv',
            'statut' => 'r.statut',
            'patient' => 'p.username',
        ];

        $sortField = $allowedSorts[$sort] ?? $allowedSorts['date'];
        $sortDirection = 'desc' === mb_strtolower($direction) ? 'DESC' : 'ASC';

        $qb->orderBy($sortField, $sortDirection)
            ->addOrderBy('r.heureRdv', 'ASC')
            ->addOrderBy('r.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   items: array<int, RendezVous>,
     *   paginator: Paginator,
     *   page: int,
     *   pages: int,
     *   total: int,
     *   limit: int
     * }
     */
    public function searchAdvanced(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $statut = $filters['statut'] ?? null;
        $typeId = $filters['type'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $sort = (string) ($filters['sort'] ?? 'dateRdv');
        $direction = mb_strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(5, min(50, (int) ($filters['limit'] ?? 10)));
        $medecinId = isset($filters['medecin_id']) ? (int) $filters['medecin_id'] : null;

        $sortField = self::ADVANCED_SORT_WHITELIST[$sort] ?? self::ADVANCED_SORT_WHITELIST['dateRdv'];

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->leftJoin('r.typeRendezVous', 't')
            ->addSelect('p', 't');

        if (null !== $medecinId && $medecinId > 0) {
            $qb->andWhere('r.medecin = :medecinId')
                ->setParameter('medecinId', $medecinId);
        }

        if ('' !== $q) {
            $term = '%'.mb_strtolower($q).'%';
            $qb->andWhere(
                'LOWER(r.titre) LIKE :term
                OR LOWER(COALESCE(p.username, \'\')) LIKE :term
                OR LOWER(COALESCE(p.email, \'\')) LIKE :term
                OR LOWER(COALESCE(t.nomType, \'\')) LIKE :term'
            )->setParameter('term', $term);
        }

        if (is_string($statut) && in_array($statut, RendezVous::STATUTS, true)) {
            $qb->andWhere('r.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if (is_int($typeId) && $typeId > 0) {
            $qb->andWhere('t.id = :typeId')
                ->setParameter('typeId', $typeId);
        }

        if ($dateFrom instanceof \DateTimeInterface) {
            $qb->andWhere('r.dateRdv >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo instanceof \DateTimeInterface) {
            $qb->andWhere('r.dateRdv <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $limit));
        if ($page > $pages) {
            $page = $pages;
        }

        $qb->orderBy($sortField, $direction);
        if ('r.dateRdv' !== $sortField) {
            $qb->addOrderBy('r.dateRdv', 'DESC');
        }
        if ('r.statut' !== $sortField) {
            $qb->addOrderBy('r.statut', 'ASC');
        }
        $qb->addOrderBy('r.heureRdv', 'ASC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), false);
        $items = iterator_to_array($paginator->getIterator(), false);

        return [
            'items' => $items,
            'paginator' => $paginator,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'limit' => $limit,
        ];
    }

    public function hasActiveConflictForMedecin(
        int $medecinId,
        \DateTimeImmutable $dateRdv,
        \DateTimeImmutable $heureRdv,
        ?int $excludeId = null
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecinId')
            ->andWhere('r.dateRdv = :dateRdv')
            ->andWhere('r.heureRdv = :heureRdv')
            ->andWhere('r.statut NOT IN (:excludedStatuses)')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('dateRdv', $dateRdv)
            ->setParameter('heureRdv', $heureRdv)
            ->setParameter('excludedStatuses', self::NON_BLOCKING_STATUSES);

        if (null !== $excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return string[]
     */
    public function findOccupiedTimesForMedecinOnDate(
        int $medecinId,
        \DateTimeImmutable $dateRdv,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('r.heureRdv AS heureRdv')
            ->andWhere('r.medecin = :medecinId')
            ->andWhere('r.dateRdv = :dateRdv')
            ->andWhere('r.statut NOT IN (:excludedStatuses)')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('dateRdv', $dateRdv)
            ->setParameter('excludedStatuses', self::NON_BLOCKING_STATUSES);

        if (null !== $excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $times = [];

        foreach ($rows as $row) {
            $rawValue = $row['heureRdv'] ?? null;
            if ($rawValue instanceof \DateTimeInterface) {
                $times[] = $rawValue->format('H:i');
                continue;
            }

            if (is_string($rawValue) && '' !== $rawValue) {
                $times[] = mb_substr($rawValue, 0, 5);
            }
        }

        return array_values(array_unique($times));
    }

    /**
     * @return array<int, array{date: string, time: string}>
     */
    public function findOccupiedSlotsForMedecinBetween(
        int $medecinId,
        \DateTimeImmutable $fromDate,
        \DateTimeImmutable $toDate
    ): array {
        $rows = $this->createQueryBuilder('r')
            ->select('r.dateRdv AS dateRdv, r.heureRdv AS heureRdv')
            ->andWhere('r.medecin = :medecinId')
            ->andWhere('r.dateRdv >= :fromDate')
            ->andWhere('r.dateRdv <= :toDate')
            ->andWhere('r.statut NOT IN (:excludedStatuses)')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->setParameter('excludedStatuses', self::NON_ACTIVE_AVAILABILITY_STATUSES)
            ->getQuery()
            ->getArrayResult();

        $occupied = [];
        foreach ($rows as $row) {
            $dateValue = $row['dateRdv'] ?? null;
            $timeValue = $row['heureRdv'] ?? null;

            $date = null;
            if ($dateValue instanceof \DateTimeInterface) {
                $date = $dateValue->format('Y-m-d');
            } elseif (is_string($dateValue) && '' !== $dateValue) {
                $date = mb_substr($dateValue, 0, 10);
            }

            $time = null;
            if ($timeValue instanceof \DateTimeInterface) {
                $time = $timeValue->format('H:i');
            } elseif (is_string($timeValue) && '' !== $timeValue) {
                $time = mb_substr($timeValue, 0, 5);
            }

            if (null !== $date && null !== $time) {
                $occupied[] = [
                    'date' => $date,
                    'time' => $time,
                ];
            }
        }

        return $occupied;
    }

    /**
     * @return array<int, array{
     *   id: int,
     *   date: string,
     *   time: string,
     *   status: string
     * }>
     */
    public function findCalendarAppointmentsBetween(
        int $medecinId,
        \DateTimeImmutable $fromDate,
        \DateTimeImmutable $toDate
    ): array {
        $rows = $this->createQueryBuilder('r')
            ->select('r.id AS id, r.dateRdv AS dateRdv, r.heureRdv AS heureRdv, r.statut AS statut')
            ->andWhere('r.medecin = :medecinId')
            ->andWhere('r.dateRdv >= :fromDate')
            ->andWhere('r.dateRdv <= :toDate')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->orderBy('r.dateRdv', 'ASC')
            ->addOrderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $appointments = [];
        foreach ($rows as $row) {
            $dateValue = $row['dateRdv'] ?? null;
            $timeValue = $row['heureRdv'] ?? null;

            $date = null;
            if ($dateValue instanceof \DateTimeInterface) {
                $date = $dateValue->format('Y-m-d');
            } elseif (is_string($dateValue) && '' !== $dateValue) {
                $date = mb_substr($dateValue, 0, 10);
            }

            $time = null;
            if ($timeValue instanceof \DateTimeInterface) {
                $time = $timeValue->format('H:i');
            } elseif (is_string($timeValue) && '' !== $timeValue) {
                $time = mb_substr($timeValue, 0, 5);
            }

            if (null === $date || null === $time) {
                continue;
            }

            $appointments[] = [
                'id' => (int) ($row['id'] ?? 0),
                'date' => $date,
                'time' => $time,
                'status' => (string) ($row['statut'] ?? ''),
            ];
        }

        return $appointments;
    }

}
