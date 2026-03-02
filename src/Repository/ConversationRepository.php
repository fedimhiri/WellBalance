<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    // Cette méthode accepte un objet User
    public function findByMedecin(User $medecin): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function getForMedecin(int $medecinId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.medecin', 'm')
            ->andWhere('m.id = :medecinId')
            ->setParameter('medecinId', $medecinId)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function countUnreadMessagesForMedecin(int $medecinId): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(m.id)')
            ->leftJoin('c.messages', 'm')
            ->leftJoin('m.sender', 's')
            ->leftJoin('c.medecin', 'med')
            ->where('med.id = :medecinId')
            ->andWhere('m.isRead = false')
            ->andWhere('s.id != :medecinId') // L'expéditeur n'est pas le médecin
            ->setParameter('medecinId', $medecinId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Cette méthode accepte un ID (int)
    public function findUrgentConversations(int $medecinId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.medecin', 'med')
            ->where('med.id = :medecinId')
            ->andWhere('c.type = :urgentType')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('urgentType', 'Urgence')
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function getMonthlyStats(int $medecinId): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');

        $currentMonthCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('c.medecin', 'med')
            ->where('med.id = :medecinId')
            ->andWhere('c.createdAt >= :startDate')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('startDate', $currentMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $lastMonthCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('c.medecin', 'med')
            ->where('med.id = :medecinId')
            ->andWhere('c.createdAt >= :startDate AND c.createdAt < :endDate')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('startDate', $lastMonth)
            ->setParameter('endDate', $currentMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'current_month' => (int)$currentMonthCount,
            'last_month' => (int)$lastMonthCount,
            'change_percent' => $lastMonthCount > 0
            ? round((($currentMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1)
            : ($currentMonthCount > 0 ? 100.0 : 0.0)
        ];
    }

    // Autres méthodes existantes...
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBetweenUsers(User $user1, User $user2): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.medecin = :user1 AND c.user = :user2) OR (c.medecin = :user2 AND c.user = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


}