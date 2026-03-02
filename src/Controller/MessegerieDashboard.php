<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $conversations = $this->conversationRepo->findByMedecin($user);
        $monthlyStats = $this->conversationRepo->getMonthlyStats($user->getId());
        $unreadMessages = $this->conversationRepo->countUnreadMessagesForMedecin($user->getId());
        $urgentConversations = $this->conversationRepo->findUrgentConversations($user->getId());
        $analyzedDocuments = $this->countAnalyzedDocuments($conversations);

        $stats = $this->buildStats(
            $user,
            $conversations,
            $unreadMessages,
            $urgentConversations,
            $analyzedDocuments,
            $monthlyStats
        );

        return $this->render('backend/admin/GesMessegerie.html.twig', [
            'stats' => $stats,
            'urgentConversations' => $urgentConversations,
            'recentConversations' => array_slice($conversations, 0, 5),
            'allConversations' => $conversations,
            'doctorId' => $user->getId(),
        ]);
    }

    #[Route('/Gestion_Messenger/export/pdf', name: 'Messenger_dashboard_export_pdf')]
    public function exportPdf(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $conversations = $this->conversationRepo->findByMedecin($user);
        $monthlyStats = $this->conversationRepo->getMonthlyStats($user->getId());
        $unreadMessages = $this->conversationRepo->countUnreadMessagesForMedecin($user->getId());
        $urgentConversations = $this->conversationRepo->findUrgentConversations($user->getId());
        $analyzedDocuments = $this->countAnalyzedDocuments($conversations);
        $stats = $this->buildStats(
            $user,
            $conversations,
            $unreadMessages,
            $urgentConversations,
            $analyzedDocuments,
            $monthlyStats
        );

        $doctorName = $user->getDisplayName() ?: $user->getUserIdentifier();
        $html = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;font-size:12px;color:#1f2937}.title{font-size:18px;font-weight:700;margin-bottom:4px}.sub{color:#6b7280;margin-bottom:12px}.grid{display:flex;flex-wrap:wrap;gap:12px}.card{border:1px solid #e5e7eb;border-radius:10px;padding:10px;min-width:180px}.label{color:#6b7280;font-size:11px}.value{font-size:20px;font-weight:700}</style></head><body><div class="title">Dashboard Messagerie</div><div class="sub">Docteur : ' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . '</div><div class="grid"><div class="card"><div class="label">Total Conversations</div><div class="value">' . $stats['totalConversations'] . '</div></div><div class="card"><div class="label">Total Messages</div><div class="value">' . $stats['totalMessages'] . '</div></div><div class="card"><div class="label">Messages non lus</div><div class="value">' . $stats['unreadMessages'] . '</div></div><div class="card"><div class="label">Urgences</div><div class="value">' . $stats['urgentMessages'] . '</div></div><div class="card"><div class="label">Docs analyses</div><div class="value">' . $stats['analyzedDocuments'] . '</div></div><div class="card"><div class="label">Taux succes IA</div><div class="value">' . $stats['aiSuccessRate'] . '%</div></div></div></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="dashboard-messagerie.pdf"',
            ]
        );
    }

    private function buildStats(
        User $user,
        array $conversations,
        int $unreadMessages,
        array $urgentConversations,
        int $analyzedDocuments,
        array $monthlyStats
    ): array {
        return [
            'totalConversations' => count($conversations),
            'totalMessages' => $this->countTotalMessages($conversations),
            'unreadMessages' => $unreadMessages,
            'urgentMessages' => count($urgentConversations),
            'analyzedDocuments' => $analyzedDocuments,
            'aiSuccessRate' => $this->calculateAiSuccessRate($conversations),
            'conversationChange' => $monthlyStats['change_percent'] ?? 0,
            'messageChange' => $this->calculateMessageChange($conversations, $user),
        ];
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

        if (0 === $totalDocs) {
            return 0.0;
        }

        return round(($successfulDocs / $totalDocs) * 100, 1);
    }

    private function calculateMessageChange(array $conversations, User $doctor): float
    {
        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
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

