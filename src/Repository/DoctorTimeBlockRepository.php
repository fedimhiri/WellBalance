<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorTimeBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorTimeBlock>
 */
class DoctorTimeBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorTimeBlock::class);
    }

    /**
     * @return DoctorTimeBlock[]
     */
    public function findForDoctorBetween(
        int $doctorId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('b')
            ->andWhere('b.doctorId = :doctorId')
            ->andWhere('b.startDatetime < :toEnd')
            ->andWhere('b.endDatetime > :fromStart')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('fromStart', $from->setTime(0, 0, 0))
            ->setParameter('toEnd', $to->setTime(23, 59, 59))
            ->orderBy('b.startDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
