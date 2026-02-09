<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function getMessagesByConversation(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function marquerCommeLu(Conversation $conversation, User $recipient): void
    {
        $qb = $this->createQueryBuilder('m')
            ->update(Message::class, 'm')
            ->set('m.isRead', ':read')
            ->where('m.conversation = :conversation')
            ->andWhere('m.sender != :recipient')
            ->andWhere('m.isRead = false')
            ->setParameter('read', true)
            ->setParameter('conversation', $conversation)
            ->setParameter('recipient', $recipient);

        $qb->getQuery()->execute();
    }

    // Méthode pour la compatibilité avec l'ancien code
    public function marquerCommeLuOldStyle(Conversation $conversation, string $typeDestinataire, int $idDestinataire): void
    {
        // Cette méthode est conservée pour la compatibilité
        // Vous pouvez l'adapter ou la supprimer selon vos besoins
    }
    // Ajouter ces méthodes
public function findNewMessages(int $conversationId, int $lastMessageId = 0): array
{
    $qb = $this->createQueryBuilder('m')
        ->where('m.conversation = :conversationId')
        ->setParameter('conversationId', $conversationId)
        ->orderBy('m.createdAt', 'ASC');
    
    if ($lastMessageId > 0) {
        $qb->andWhere('m.id > :lastMessageId')
           ->setParameter('lastMessageId', $lastMessageId);
    }
    
    return $qb->getQuery()->getResult();
}

public function findUnreadMessages(int $conversationId, int $userId): array
{
    return $this->createQueryBuilder('m')
        ->join('m.conversation', 'c')
        ->where('c.id = :conversationId')
        ->andWhere('m.sender != :userId')
        ->andWhere('m.isRead = false')
        ->setParameter('conversationId', $conversationId)
        ->setParameter('userId', $userId)
        ->getQuery()
        ->getResult();
}
}