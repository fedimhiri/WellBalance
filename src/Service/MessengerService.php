<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class MessengerService
{
    private CryptoService $crypto;

    public function __construct(CryptoService $crypto)
    {
        $this->crypto = $crypto;
    }
    /**
     * Checks if the client needs a refresh based on the last update timestamp.
     */
    public function shouldRefresh(Conversation $conversation, int $clientLastUpdate): bool
    {
        $serverTimestamp = $conversation->getUpdatedAt()->getTimestamp();
        return $serverTimestamp > $clientLastUpdate;
    }

    /**
     * Serializes messages for JSON response.
     *
     * @param Message[] $messages
     * @return array
     */
    public function serializeMessages(array $messages): array
    {
        $data = [];
        foreach ($messages as $message) {
            $content = $this->normalizeContent($message->getContent());
            $data[] = [
                'id' => $message->getId(),
                'content' => $content,
                'created_at' => $message->getCreatedAt()->format('c'),
                'sender_id' => $message->getSender()->getId(),
                'sender_role' => in_array('ROLE_ADMIN', $message->getSender()->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER',
                'sender_name' => $message->getSender()->getUserIdentifier(), // Or getUsername() if available
                'is_read' => $message->isRead(),
                'attachment' => $message->getAttachment(),
                'ai_analysis' => $message->getAiAnalysis()
            ];
        }
        return $data;
    }

    public function normalizeContent(?string $content): ?string
    {
        if ($content !== null && $content !== '' && str_starts_with($content, 'v1:gcm:')) {
            return $this->crypto->decrypt($content);
        }
        return $content;
    }

    /**
     * Sorts messages by creation date.
     */
    public function sortMessages(array $messages): array
    {
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());
        return $messages;
    }
}
