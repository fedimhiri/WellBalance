<?php

namespace App\Controller\Api;

use App\Service\NutritionChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat')]
#[IsGranted('ROLE_USER')]
final class ChatController extends AbstractController
{
    #[Route('', name: 'api_chat_ask', methods: ['POST'])]
    public function ask(Request $request, NutritionChatService $chat): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];
            $msg = trim((string)($payload['message'] ?? ''));
            $prev = $payload['previous_response_id'] ?? null;

            if ($msg === '') {
                return $this->json(["ok" => false, "error" => "Message vide"], 400);
            }

            $user = $this->getUser();
            $userId = method_exists($user, 'getId') ? (int)$user->getId() : 0;

            $out = $chat->ask($userId, $msg, $prev);

            return $this->json([
                "ok" => true,
                "answer" => $out["answer"],
                "previous_response_id" => $out["response_id"],
            ]);
        } catch (\Throwable $e) {
            // ✅ au lieu d’un 500 “Erreur réseau”, tu verras le message exact côté front
            return $this->json([
                "ok" => false,
                "error" => "SERVER_ERROR: ".$e->getMessage(),
            ], 500);
        }
    }
}