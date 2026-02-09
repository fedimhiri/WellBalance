<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class TypingManager
{
    private $requestStack;
    
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }
    
    public function setTypingStatus(int $conversationId, int $userId, bool $isTyping): void
    {
        $session = $this->requestStack->getSession();
        
        if ($isTyping) {
            $session->set("typing_{$conversationId}_{$userId}", time());
        } else {
            $session->remove("typing_{$conversationId}_{$userId}");
        }
    }
    
    public function getTypingUsers(int $conversationId, int $excludeUserId = null): array
    {
        $session = $this->requestStack->getSession();
        $typingUsers = [];
        
        foreach ($session->all() as $key => $timestamp) {
            if (str_starts_with($key, "typing_{$conversationId}_")) {
                $userId = (int) str_replace("typing_{$conversationId}_", '', $key);
                
                // Vérifier si c'est encore récent (moins de 5 secondes)
                if (time() - $timestamp < 5) {
                    if (!$excludeUserId || $userId != $excludeUserId) {
                        $typingUsers[] = $userId;
                    }
                } else {
                    // Nettoyer les anciennes entrées
                    $session->remove($key);
                }
            }
        }
        
        return $typingUsers;
    }
}