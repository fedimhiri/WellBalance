<?php

namespace App\Repository;

use App\Entity\ActivitePhysique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivitePhysique>
 *
 * @method ActivitePhysique|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivitePhysique|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActivitePhysique[]    findAll()
 * @method ActivitePhysique[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivitePhysiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivitePhysique::class);
    }

    public function save(ActivitePhysique $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivitePhysique $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function search(string $query = null, string $sort = 'a.id', string $direction = 'DESC')
    {
        $qb = $this->createQueryBuilder('a');

        if ($query) {
             $qb->andWhere('a.nom LIKE :query OR a.description LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        return $qb->orderBy($sort, $direction)
            ->getQuery()
            ->getResult();
    }
}
