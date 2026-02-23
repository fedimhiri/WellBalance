<?php

namespace App\Repository;

use App\Entity\ObjectifSportif;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectifSportif>
 *
 * @method ObjectifSportif|null find($id, $lockMode = null, $lockVersion = null)
 * @method ObjectifSportif|null findOneBy(array $criteria, array $orderBy = null)
 * @method ObjectifSportif[]    findAll()
 * @method ObjectifSportif[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ObjectifSportifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectifSportif::class);
    }

    public function save(ObjectifSportif $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ObjectifSportif $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function search(string $query = null, string $sort = 'o.id', string $direction = 'DESC')
    {
        $qb = $this->createQueryBuilder('o');

        if ($query) {
            $qb->andWhere('o.libelle LIKE :query OR o.description LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        return $qb->orderBy($sort, $direction)
            ->getQuery()
            ->getResult();
    }
}
