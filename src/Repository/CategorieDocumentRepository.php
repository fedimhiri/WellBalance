<?php

namespace App\Repository;

use App\Entity\CategorieDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorieDocument>
 */
class CategorieDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieDocument::class);
    }

    public function searchAndSort(?string $search, string $sort = 'id', string $dir = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(c.description) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $allowedSort = ['id', 'description'];
        $sortField = in_array($sort, $allowedSort, true) ? 'c.' . $sort : 'c.id';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $qb->orderBy($sortField, $direction);

        return $qb->getQuery()->getResult();
    }
}
