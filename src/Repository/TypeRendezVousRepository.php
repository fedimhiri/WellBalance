<?php

namespace App\Repository;

use App\Entity\TypeRendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeRendezVous>
 */
class TypeRendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeRendezVous::class);
    }

    /**
     * @return TypeRendezVous[]
     */
    public function searchList(?string $query = null, string $sort = 'nom', string $direction = 'asc'): array
    {
        $qb = $this->createQueryBuilder('t');

        if (null !== $query && '' !== trim($query)) {
            $normalized = trim($query);
            $term = '%'.mb_strtolower($normalized).'%';
            $qb->andWhere(
                'LOWER(t.nomType) LIKE :term
                OR LOWER(COALESCE(t.description, \'\')) LIKE :term'
            )
                ->setParameter('term', $term);

            if (ctype_digit($normalized)) {
                $qb->orWhere('t.id = :exactId')
                    ->setParameter('exactId', (int) $normalized);
            }
        }

        $allowedSorts = [
            'id' => 't.id',
            'nom' => 't.nomType',
            'description' => 't.description',
        ];

        $sortField = $allowedSorts[$sort] ?? $allowedSorts['nom'];
        $sortDirection = 'desc' === mb_strtolower($direction) ? 'DESC' : 'ASC';

        $qb->orderBy($sortField, $sortDirection)
            ->addOrderBy('t.id', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
