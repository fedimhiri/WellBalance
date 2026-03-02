<?php

namespace App\Service;

use App\Entity\Conversation;

class JitsiService
{
    private string $domain;
    private string $secret;

    public function __construct(string $jitsiDomain, string $hmacSecret = '')
    {
        $this->domain = $jitsiDomain ?: 'meet.jit.si';
        $this->secret = $hmacSecret ?: 'wellbalance';
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRoomName(Conversation $conversation): string
    {
        $base = 'WB-' . base_convert((string)$conversation->getId(), 10, 36);
        $payload = $conversation->getId() . '|' . ($conversation->getCreatedAt() ? $conversation->getCreatedAt()->format('c') : '');
        $hash = substr(hash_hmac('sha256', $payload, $this->secret), 0, 12);
        return $base . '-' . $hash;
    }
}
