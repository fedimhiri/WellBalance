<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorRecurringAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorRecurringAvailability>
 */
class DoctorRecurringAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorRecurringAvailability::class);
    }

    /**
     * @return DoctorRecurringAvailability[]
     */
    public function findForDoctor(int $doctorId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.doctorId = :doctorId')
            ->setParameter('doctorId', $doctorId)
            ->orderBy('r.weekday', 'ASC')
            ->addOrderBy('r.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
