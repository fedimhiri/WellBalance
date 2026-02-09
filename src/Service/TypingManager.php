<?php

namespace App\Service;

class TypingManager
{
    private $storageDir;
    
    public function __construct(string $projectDir)
    {
        $this->storageDir = $projectDir . '/var/typing';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }
    
    public function setTypingStatus(int $conversationId, int $userId, bool $isTyping): void
    {
        $file = $this->storageDir . "/conv_{$conversationId}.json";
        
        // Récupérer l'état actuel
        $state = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $state = json_decode($content, true) ?: [];
        }
        
        // Mise à jour
        if ($isTyping) {
            $state[$userId] = time();
        } else {
            unset($state[$userId]);
        }
        
        // Sauvegarde
        file_put_contents($file, json_encode($state));
    }
    
    public function getTypingUsers(int $conversationId, int $userId = null): array
    {
        $file = $this->storageDir . "/conv_{$conversationId}.json";
        
        $state = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $state = json_decode($content, true) ?: [];
        }

        $activeUsers = [];
        $now = time();
        $cleanedState = [];
        
        foreach ($state as $uid => $timestamp) {
            // Valide si activité < 10 secondes
            if ($now - $timestamp < 10) {
                if (!$userId || $uid != $userId) {
                    $activeUsers[] = (int)$uid;
                }
                $cleanedState[$uid] = $timestamp;
            }
        }
        
        // Nettoyer les entrées expirées
        if (count($cleanedState) !== count($state)) {
            file_put_contents($file, json_encode($cleanedState));
        }
        
        return $activeUsers;
    }
}
