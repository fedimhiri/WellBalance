<?php

namespace App\Repository;

use App\Entity\PlanNutrition;
use App\Entity\SuiviNutrition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SuiviNutritionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviNutrition::class);
    }

    /** @return SuiviNutrition[] */
    public function findForPatientPlan(User $patient, PlanNutrition $plan): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.patient = :p')->setParameter('p', $patient)
            ->andWhere('s.planNutrition = :plan')->setParameter('plan', $plan)
            ->orderBy('s.dateSuivi', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return SuiviNutrition[] */
    public function lastDays(PlanNutrition $plan, int $days): array
    {
        $from = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.planNutrition = :plan')->setParameter('plan', $plan)
            ->andWhere('s.dateSuivi >= :from')->setParameter('from', $from)
            ->orderBy('s.dateSuivi', 'ASC')
            ->getQuery()->getResult();
    }

    public function lastSuiviDate(PlanNutrition $plan): ?\DateTimeInterface
    {
        $row = $this->createQueryBuilder('s')
            ->select('MAX(s.dateSuivi) as maxDate')
            ->andWhere('s.planNutrition = :plan')->setParameter('plan', $plan)
            ->getQuery()->getOneOrNullResult();

        return $row && $row['maxDate'] ? new \DateTime($row['maxDate']) : null;
    }

    /** ✅ AJOUT: récupérer les N derniers suivis (DESC) */
    /** @return SuiviNutrition[] */
    public function lastN(PlanNutrition $plan, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        return $this->createQueryBuilder('s')
            ->andWhere('s.planNutrition = :plan')->setParameter('plan', $plan)
            ->orderBy('s.dateSuivi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
