<?php

namespace App\Repository;

use App\Entity\AlerteNutrition;
use App\Entity\PlanNutrition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlerteNutritionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlerteNutrition::class);
    }

    /**
     * Vérifie si une alerte ouverte existe déjà
     */
    public function existsOpen(string $type, PlanNutrition $plan, User $patient): bool
    {
        $count = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.type = :t')->setParameter('t', $type)
            ->andWhere('a.planNutrition = :plan')->setParameter('plan', $plan)
            ->andWhere('a.patient = :p')->setParameter('p', $patient)
            ->andWhere('a.resolvedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Retourne une alerte ouverte précise (utile pour update)
     */
    public function findOpen(string $type, PlanNutrition $plan, User $patient): ?AlerteNutrition
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.type = :t')->setParameter('t', $type)
            ->andWhere('a.planNutrition = :plan')->setParameter('plan', $plan)
            ->andWhere('a.patient = :p')->setParameter('p', $patient)
            ->andWhere('a.resolvedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Alertes ouvertes d’un patient (tous plans confondus)
     * @return AlerteNutrition[]
     */
    public function openAlertsForPatient(User $patient): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.patient = :p')->setParameter('p', $patient)
            ->andWhere('a.resolvedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alertes ouvertes d’un nutritionniste
     * @return AlerteNutrition[]
     */
    public function openAlertsForNutritionniste(User $nutritionniste): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nutritionniste = :n')->setParameter('n', $nutritionniste)
            ->andWhere('a.resolvedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alertes ouvertes pour un plan spécifique
     * @return AlerteNutrition[]
     */
    public function openAlertsForPlan(PlanNutrition $plan): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.planNutrition = :plan')->setParameter('plan', $plan)
            ->andWhere('a.resolvedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alertes ouvertes pour un plan + patient (front sécurisé)
     * @return AlerteNutrition[]
     */
    public function openAlertsForPlanAndPatient(PlanNutrition $plan, User $patient): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.planNutrition = :plan')->setParameter('plan', $plan)
            ->andWhere('a.patient = :p')->setParameter('p', $patient)
            ->andWhere('a.resolvedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
