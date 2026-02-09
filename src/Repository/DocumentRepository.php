<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function searchAdvanced(
        $user,
        ?string $search,
        ?int $categorieId,
        ?string $type,
        string $sort,
        string $dir
    ): array {
        // SECURITY: allow only known fields
        $allowedSorts = ['dateUpload', 'titre', 'typeDocument'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'dateUpload';
        }

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.categorie', 'c')
            ->addSelect('c')
            ->where('d.user = :user')
            ->setParameter('user', $user);

        if ($search) {
            $qb->andWhere('d.titre LIKE :q OR d.typeDocument LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($categorieId) {
            $qb->andWhere('c.id = :cat')
               ->setParameter('cat', $categorieId);
        }

        if ($type) {
            $qb->andWhere('d.typeDocument = :type')
               ->setParameter('type', $type);
        }

        $qb->orderBy('d.' . $sort, $dir);

        return $qb->getQuery()->getResult();
    }
}
