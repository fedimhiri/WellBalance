<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MessegerieDashboard extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepo,
        private readonly MessageRepository $messageRepo,
    ) {
    }

    #[Route('/Gestion_Messenger', name: 'Messenger_dashboard')]
    public function index(): Response
    {
        // Récupérer l'utilisateur connecté (médecin)
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer toutes les conversations pour ce médecin
        $conversations = $this->conversationRepo->findByDoctor($user); // Utilisez findByDoctor au lieu de getForDoctor
        
        // Récupérer les stats mensuelles
        $monthlyStats = $this->conversationRepo->getMonthlyStats($user->getId());
        
        // Compter les messages non lus
        $unreadMessages = $this->conversationRepo->countUnreadMessagesForDoctor($user->getId());
        
        // Récupérer les conversations urgentes
        $urgentConversations = $this->conversationRepo->findUrgentConversations($user->getId());
        
        // Calculer le nombre de documents analysés
        $analyzedDocuments = $this->countAnalyzedDocuments($conversations);

        // Préparer les stats
        $stats = [
            'totalConversations' => count($conversations),
            'totalMessages' => $this->countTotalMessages($conversations),
            'unreadMessages' => $unreadMessages,
            'urgentMessages' => count($urgentConversations),
            'analyzedDocuments' => $analyzedDocuments,
            'aiSuccessRate' => $this->calculateAiSuccessRate($conversations),
            'conversationChange' => $monthlyStats['change_percent'] ?? 0,
            'messageChange' => $this->calculateMessageChange($conversations, $user),
        ];

        return $this->render('backend/admin/GesMessegerie.html.twig', [
            'stats' => $stats,
            'urgentConversations' => $urgentConversations,
            'recentConversations' => array_slice($conversations, 0, 5),
            'allConversations' => $conversations,
            'doctorId' => $user->getId(),
        ]);
    }

    private function countTotalMessages(array $conversations): int
    {
        $total = 0;
        foreach ($conversations as $conversation) {
            $total += $conversation->getMessages()->count();
        }
        return $total;
    }

    private function countAnalyzedDocuments(array $conversations): int
    {
        $count = 0;
        foreach ($conversations as $conversation) {
            foreach ($conversation->getMessages() as $message) {
                if ($message->getAttachment() && $message->hasSuccessfulAnalysis()) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function calculateAiSuccessRate(array $conversations): float
    {
        $totalDocs = 0;
        $successfulDocs = 0;

        foreach ($conversations as $conversation) {
            foreach ($conversation->getMessages() as $message) {
                if ($message->getAttachment()) {
                    $totalDocs++;
                    if ($message->hasSuccessfulAnalysis()) {
                        $successfulDocs++;
                    }
                }
            }
        }

        if ($totalDocs == 0) {
            return 0.0;
        }

        return round(($successfulDocs / $totalDocs) * 100, 1);
    }

    private function calculateMessageChange(array $conversations, User $doctor): float
    {
        // Calculer le pourcentage de changement des messages par rapport au mois précédent
        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
        $twoMonthsAgo = new \DateTime('first day of last month -1 month');
        
        // Messages du mois en cours
        $currentMonthMessages = 0;
        $lastMonthMessages = 0;
        
        foreach ($conversations as $conversation) {
            foreach ($conversation->getMessages() as $message) {
                $messageDate = $message->getCreatedAt();
                if ($messageDate >= $currentMonth) {
                    $currentMonthMessages++;
                } elseif ($messageDate >= $lastMonth && $messageDate < $currentMonth) {
                    $lastMonthMessages++;
                }
            }
        }
        
        if ($lastMonthMessages > 0) {
            return round((($currentMonthMessages - $lastMonthMessages) / $lastMonthMessages) * 100, 1);
        }
        
        return $currentMonthMessages > 0 ? 100.0 : 0.0;
    }

    #[Route('/myprofile', name: 'admin_profile')]
    public function profile(): Response
    {
        return $this->render('backend/admin/profile.html.twig');
    }

}