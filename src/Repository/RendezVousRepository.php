<?php

namespace App\Repository;

use App\Entity\RendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }
    public function searchAndSort(?string $search, string $sort, string $dir): array
{
    $qb = $this->createQueryBuilder('r')
        ->leftJoin('r.type', 't')
        ->addSelect('t');

    if ($search) {
        $qb->andWhere('
            r.titre LIKE :q OR
            r.lieu LIKE :q OR
            r.notes LIKE :q OR
            r.statut LIKE :q OR
            t.libelle LIKE :q
        ')
        ->setParameter('q', '%'.$search.'%');
    }

    // Whitelist tri (sécurité)
    $sortMap = [
        'id' => 'r.id',
        'titre' => 'r.titre',
        'dateRdv' => 'r.dateRdv',
        'lieu' => 'r.lieu',
        'statut' => 'r.statut',
        'type' => 't.libelle',
    ];

    $sortField = $sortMap[$sort] ?? 'r.id';
    $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

    $qb->orderBy($sortField, $direction);

    return $qb->getQuery()->getResult();
}

}
