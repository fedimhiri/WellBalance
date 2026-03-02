<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorAvailabilityException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorAvailabilityException>
 */
class DoctorAvailabilityExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorAvailabilityException::class);
    }

    /**
     * @return DoctorAvailabilityException[]
     */
    public function findForDoctorBetween(
        int $doctorId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('e')
            ->andWhere('e.doctorId = :doctorId')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
