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
    public function findByDoctor(User $doctor): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function getForDoctor(int $doctorId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.doctor', 'd')
            ->andWhere('d.id = :doctorId')
            ->setParameter('doctorId', $doctorId)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function countUnreadMessagesForDoctor(int $doctorId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(m.id)')
            ->leftJoin('c.messages', 'm')
            ->leftJoin('m.sender', 's')
            ->leftJoin('c.doctor', 'd')
            ->where('d.id = :doctorId')
            ->andWhere('m.isRead = false')
            ->andWhere('s.id != :doctorId') // L'expéditeur n'est pas le docteur
            ->setParameter('doctorId', $doctorId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Cette méthode accepte un ID (int)
    public function findUrgentConversations(int $doctorId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.doctor', 'd')
            ->where('d.id = :doctorId')
            ->andWhere('c.type = :urgentType')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('urgentType', 'Urgence')
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Cette méthode accepte un ID (int)
    public function getMonthlyStats(int $doctorId): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
        
        $currentMonthCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('c.doctor', 'd')
            ->where('d.id = :doctorId')
            ->andWhere('c.createdAt >= :startDate')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('startDate', $currentMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $lastMonthCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('c.doctor', 'd')
            ->where('d.id = :doctorId')
            ->andWhere('c.createdAt >= :startDate AND c.createdAt < :endDate')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('startDate', $lastMonth)
            ->setParameter('endDate', $currentMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'current_month' => (int) $currentMonthCount,
            'last_month' => (int) $lastMonthCount,
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
            ->andWhere('(c.doctor = :user1 AND c.user = :user2) OR (c.doctor = :user2 AND c.user = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}