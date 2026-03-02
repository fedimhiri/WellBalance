<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }


    public function searchAndSort(?string $search, ?string $sort): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($search) {
            $qb->andWhere(
                'u.email LIKE :search OR u.username LIKE :search OR u.telephone LIKE :search'
            )
                ->setParameter('search', '%' . $search . '%');
        }

        switch ($sort) {
            case 'email':
                $qb->orderBy('u.email', 'ASC');
                break;
            case 'username':
                $qb->orderBy('u.username', 'ASC');
                break;
            default:
                $qb->orderBy('u.id', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getResult();
    }
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getResult();
    }

    public function createUsersQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.username', 'ASC');
    }

    public function createMedecinsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :medecinRole')
            ->setParameter('medecinRole', '%"ROLE_MEDECIN"%')
            ->orderBy('u.username', 'ASC');
    }

    public function createPatientsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :patientRole')
            ->setParameter('patientRole', '%"ROLE_PATIENT"%')
            ->orderBy('u.username', 'ASC');
    }



//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
