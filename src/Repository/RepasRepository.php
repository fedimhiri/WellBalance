<?php

namespace App\Repository;

use App\Entity\Repas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Repas::class);
    }

    /**
     * @return Repas[]
     */
    public function findWithFilters(?string $type, ?string $date, int $planId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.planNutrition', 'p')
            ->addSelect('p')
            ->orderBy('r.dateRepas', 'DESC');

        if ($type) {
            $qb->andWhere('r.typeRepas = :type')
               ->setParameter('type', $type);
        }

        // Filtre par date (format attendu: YYYY-MM-DD)
        if ($date) {
            try {
                $dayStart = new \DateTimeImmutable($date . ' 00:00:00');
                $dayEnd   = new \DateTimeImmutable($date . ' 23:59:59');

                $qb->andWhere('r.dateRepas BETWEEN :dayStart AND :dayEnd')
                   ->setParameter('dayStart', $dayStart)
                   ->setParameter('dayEnd', $dayEnd);
            } catch (\Exception $e) {
                // si date invalide, on ignore le filtre
            }
        }

        if ($planId > 0) {
            $qb->andWhere('p.id = :planId')
               ->setParameter('planId', $planId);
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * ✅ Derniers repas d’un plan (contexte Chatbot)
     * @return Repas[]
     */
    public function findLastForPlan(int $planId, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.planNutrition = :pid')
            ->setParameter('pid', $planId)
            ->orderBy('r.dateRepas', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Calories total aujourd'hui (tous users)
     * (tu l'avais déjà)
     */
    public function getTodayTotalCalories(): int
    {
        $todayStart = new \DateTimeImmutable('today 00:00:00');
        $todayEnd   = new \DateTimeImmutable('today 23:59:59');

        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.calories), 0) as total')
            ->andWhere('r.dateRepas BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ NEW : Calories total aujourd'hui pour un user (via son plan)
     */
    public function getTodayTotalCaloriesForUser(int $userId): int
    {
        $todayStart = new \DateTimeImmutable('today 00:00:00');
        $todayEnd   = new \DateTimeImmutable('today 23:59:59');

        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.calories), 0) as total')
            ->leftJoin('r.planNutrition', 'p')
            ->leftJoin('p.user', 'u')
            ->andWhere('u.id = :uid')
            ->andWhere('r.dateRepas BETWEEN :start AND :end')
            ->setParameter('uid', $userId)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteMultiple(array $ids): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}