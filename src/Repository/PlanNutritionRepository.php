<?php

namespace App\Repository;

use App\Entity\PlanNutrition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

class PlanNutritionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanNutrition::class);
    }

    /**
     * Recherche ADMIN (BackOffice)
     * - q : recherche texte (objectif, description, nom user, email)
     * - objectif : filtre objectif exact
     * - statut : actif | termine | null
     */
    public function searchAdmin(?string $q = null, ?string $objectif = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.dateDebut', 'DESC');

        if ($objectif) {
            $qb->andWhere('p.objectif = :objectif')
                ->setParameter('objectif', $objectif);
        }

        if ($q) {
            $q = trim($q);
            $qb->andWhere('
                p.objectif LIKE :q OR
                p.description LIKE :q OR
                u.nom LIKE :q OR
                u.email LIKE :q
            ')
            ->setParameter('q', '%'.$q.'%');
        }

        if ($statut === 'actif') {
            $qb->andWhere('p.dateFin >= :today')
               ->setParameter('today', new \DateTimeImmutable('today'));
        } elseif ($statut === 'termine') {
            $qb->andWhere('p.dateFin < :today')
               ->setParameter('today', new \DateTimeImmutable('today'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche FRONT (User) : uniquement les plans du user connecté
     */
    public function searchForUser(UserInterface $user, ?string $q = null, ?string $objectif = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateDebut', 'DESC');

        if ($objectif) {
            $qb->andWhere('p.objectif = :objectif')
                ->setParameter('objectif', $objectif);
        }

        if ($q) {
            $q = trim($q);
            $qb->andWhere('(p.objectif LIKE :q OR p.description LIKE :q)')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($statut === 'actif') {
            $qb->andWhere('p.dateFin >= :today')
               ->setParameter('today', new \DateTimeImmutable('today'));
        } elseif ($statut === 'termine') {
            $qb->andWhere('p.dateFin < :today')
               ->setParameter('today', new \DateTimeImmutable('today'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Liste unique des objectifs (pour remplir le select)
     */
    public function getDistinctObjectifs(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.objectif AS objectif')
            ->where('p.objectif IS NOT NULL')
            ->andWhere('p.objectif <> :empty')
            ->setParameter('empty', '')
            ->orderBy('p.objectif', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn ($r) => $r['objectif'] ?? null, $rows)));
    }

    /**
     * ✅ STATS GLOBAL (ADMIN)
     */
    public function getGlobalStats(): array
    {
        $today = new \DateTimeImmutable('today');

        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $actifs = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.dateFin >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        $termines = $total - $actifs;

        return [
            'total' => $total,
            'actifs' => $actifs,
            'termines' => max(0, $termines),
        ];
    }

    /**
     * ✅ STATS par objectif (ADMIN)
     * Retour: [ ['objectif'=>'Perte de poids','count'=>10], ... ]
     */
    public function getStatsByObjectif(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.objectif AS objectif, COUNT(p.id) AS count')
            ->where('p.objectif IS NOT NULL')
            ->andWhere('p.objectif <> :empty')
            ->setParameter('empty', '')
            ->groupBy('p.objectif')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * ✅ STATS plans par mois (dernier N mois) (ADMIN)
     * Retour: labels[] + values[]
     */
    public function getPlansPerMonth(int $months = 6): array
    {
        $months = max(1, min(24, $months));

        // date de début = 1er jour du mois (N-1 mois en arrière)
        $start = (new \DateTimeImmutable('first day of this month 00:00:00'))
            ->modify('-'.($months - 1).' months');

        $rows = $this->createQueryBuilder('p')
            ->select('p.dateDebut AS dateDebut')
            ->where('p.dateDebut >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getArrayResult();

        // init tableau mois
        $map = [];
        $cursor = $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $map[$key] = 0;
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($rows as $r) {
            if (!isset($r['dateDebut']) || !$r['dateDebut'] instanceof \DateTimeInterface) {
                continue;
            }
            $key = $r['dateDebut']->format('Y-m');
            if (array_key_exists($key, $map)) {
                $map[$key]++;
            }
        }

        $labels = [];
        $values = [];
        foreach ($map as $ym => $count) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ym.'-01');
            $labels[] = $dt ? $dt->format('M Y') : $ym;
            $values[] = $count;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
