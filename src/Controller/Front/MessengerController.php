<?php

namespace App\Controller\Front;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TypingManager;
use App\Service\MessengerService;
use App\Service\DocumentAnalyzerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\JitsiService;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Service\MailerService;

#[Route('/Vue/messenger', name: 'front_messenger_')]
class MessengerController extends AbstractController
{
    private $messengerService;
    private $typingManager;
    private $slugger;
    private $documentAnalyzer;
    private JitsiService $jitsi;
    private $mailerService;

    public function __construct(
        MessengerService $messengerService,
        TypingManager $typingManager,
        SluggerInterface $slugger,
        DocumentAnalyzerService $documentAnalyzer,
        JitsiService $jitsi,
        MailerService $mailerService
    ) {
        $this->messengerService   = $messengerService;
        $this->typingManager      = $typingManager;
        $this->slugger            = $slugger;
        $this->documentAnalyzer   = $documentAnalyzer;
        $this->jitsi              = $jitsi;
        $this->mailerService      = $mailerService;
    }
    #[Route('/listes', name: 'index', methods: ['GET'])]
    public function index(Request $request, ConversationRepository $conversationRepo, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Si c'est un admin, on le redirige
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('admin_messenger_index');
        }

        $conversations = $conversationRepo->findByUser($user);

        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $conversations = array_filter($conversations, function($c) use ($needle, $user) {
                $parts = [];
                if ($c->getUser()) {
                    $parts[] = $c->getUser()->getDisplayName();
                }
                if ($c->getMedecin()) {
                    $parts[] = $c->getMedecin()->getDisplayName();
                }
                if ($c->getType()) {
                    $parts[] = $c->getType();
                }
                if ($c->getSujet()) {
                    $parts[] = $c->getSujet();
                }
                $unreadCount = $c->getUnreadCountFor($user);
                $parts[] = $unreadCount > 0 ? $unreadCount . ' non lu(s)' : 'à jour';
                $haystack = mb_strtolower(implode(' ', $parts));
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
                foreach ($c->getMessages() as $m) {
                    $content = (string) $this->messengerService->normalizeContent($m->getContent());
                    if ($content !== '' && str_contains(mb_strtolower($content), $needle)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $allowedSortBy = ['updatedAt', 'createdAt', 'type'];
        $sortBy = (string) $request->query->get('sortBy', 'updatedAt');
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'updatedAt';
        }

        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC'));
        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        usort($conversations, function($a, $b) use ($sortBy, $sortOrder) {
            $valA = $sortBy === 'updatedAt' ? $a->getUpdatedAt() : $a->getCreatedAt();
            $valB = $sortBy === 'updatedAt' ? $b->getUpdatedAt() : $b->getCreatedAt();

            if ($sortBy === 'type') {
                $valA = $a->getType() ?? '';
                $valB = $b->getType() ?? '';
            }

            if ($sortOrder === 'DESC') {
                return $valB <=> $valA;
            }
            return $valA <=> $valB;
        });

        // Formulaire pour nouvelle conversation
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message, [
            'is_new_conversation' => true,
            'available_admins' => $userRepository->findAdmins(),
        ]);

        $isAjax = $request->query->getBoolean('ajax')
            || $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');

        if ($isAjax) {
            $data = [];
            foreach ($conversations as $conv) {
                $lastMessage = $conv->getLastMessage();
                $lastContent = $lastMessage ? (string) $this->messengerService->normalizeContent($lastMessage->getContent()) : '';
                $short = $lastContent !== '' ? $lastContent : '';
                $unreadCount = $conv->getUnreadCountFor($user);

                $data[] = [
                    'id' => $conv->getId(),
                    'medecin_name' => $conv->getMedecin() ? $conv->getMedecin()->getDisplayName() : '',
                    'type' => $conv->getType() ?? 'Général',
                    'sujet' => $conv->getSujet() ?? 'Aucun sujet',
                    'last_message' => $short,
                    'has_message' => $lastContent !== '',
                    'date' => $conv->getUpdatedAt() ? $conv->getUpdatedAt()->format('d/m/Y H:i') : '',
                    'show_url' => $this->generateUrl('front_messenger_show', ['id' => $conv->getId()]),
                    'unread_count' => $unreadCount,
                ];
            }

            return new JsonResponse([
                'conversations' => $data,
                'total' => count($data),
                'search' => $search,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ]);
        }

        return $this->render('messenger/front_index.html.twig', [
            'conversations' => $conversations,
            'form' => $form->createView(),
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'viewer' => $user,
        ]);
    }

    #[Route('/conversation/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, Request $request, ConversationRepository $conversationRepo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('admin_messenger_show', ['id' => $id]);
        }

        $conversation = $conversationRepo->find($id);

        // VÃ©rification simple
        if (!$conversation || $conversation->getUser() !== $user) {
            return $this->redirectToRoute('front_messenger_index');
        }

        // Marquer les messages comme lus
        foreach ($conversation->getMessages() as $msg) {
            if ($msg->getSender() !== $user && !$msg->isRead()) {
                $msg->setRead(true);
            }
        }
        $em->flush();

        // RÃ©cupÃ©rer et trier les messages
        $messages = $conversation->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        // Recherche dans les messages
        $search = trim((string) $request->query->get('searchMsg', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $messages = array_filter($messages, fn($m) => str_contains(mb_strtolower((string) $this->messengerService->normalizeContent($m->getContent())), $needle));
        }
        $messages = array_values($messages);
        foreach ($messages as $message) {
            $message->setContent($this->messengerService->normalizeContent($message->getContent()));
        }

        // Formulaire
        $form = $this->createForm(MessageType::class, new Message(), ['is_new_conversation' => false]);

        // Navigation (PrÃ©cÃ©dent / Suivant)
        $allConversations = $conversationRepo->findByUser($user, ['updatedAt' => 'DESC']);
        $currentIndex = array_search($conversation, $allConversations);
        $prevConversation = $allConversations[$currentIndex - 1] ?? null;
        $nextConversation = $allConversations[$currentIndex + 1] ?? null;

        return $this->render('messenger/front_show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'form' => $form->createView(),
            'viewer' => $user,
            'viewerType' => 'user',
            'searchMsg' => $search,
            'prevConv' => $prevConversation,
            'nextConv' => $nextConversation,
            'jitsiRoom' => $this->jitsi->getRoomName($conversation),
            'jitsiDomain' => $this->jitsi->getDomain(),
        ]);
    }

    #[Route('/conversation/{id}/meet', name: 'meet', methods: ['GET'])]
    public function meet(int $id, ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $conversation = $conversationRepo->find($id);
        if (!$conversation || $conversation->getUser() !== $user) {
            return $this->redirectToRoute('front_messenger_index');
        }
        $room = $this->jitsi->getRoomName($conversation);
        return $this->render('messenger/meet.html.twig', [
            'room' => $room,
            'domain' => $this->jitsi->getDomain(),
            'displayName' => $user->getDisplayName() ?: $user->getUserIdentifier(),
            'base_template' => 'frontend/base_frontend.html.twig',
        ]);
    }

    #[Route('/conversation/{id}/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(int $id, ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');
        $conversation = $conversationRepo->find($id);
        if (!$conversation || $conversation->getUser() !== $user) {
            return $this->redirectToRoute('front_messenger_index');
        }

        $messages = $conversation->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $patientName = $conversation->getUser() ? $conversation->getUser()->getDisplayName() : '';
        $doctorName = $conversation->getMedecin() ? $conversation->getMedecin()->getDisplayName() : '';
        $subject = $conversation->getSujet() ?? 'Aucun sujet';

        $rows = '';
        foreach ($messages as $message) {
            $sender = $message->getSender() ? $message->getSender()->getDisplayName() : '';
            $date = $message->getCreatedAt() ? $message->getCreatedAt()->format('d/m/Y H:i') : '';
            $content = $this->messengerService->normalizeContent($message->getContent()) ?? '';
            $contentHtml = $content !== '' ? nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) : '';
            $attachment = $message->getAttachment();
            $attachmentHtml = $attachment ? '<div class="attachment">Pièce jointe : ' . htmlspecialchars($attachment, ENT_QUOTES, 'UTF-8') . '</div>' : '';
            $rows .= '<div class="message"><div class="meta"><span class="sender">' . htmlspecialchars($sender, ENT_QUOTES, 'UTF-8') . '</span><span class="date">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</span></div><div class="content">' . $contentHtml . '</div>' . $attachmentHtml . '</div>';
        }

        $html = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;font-size:12px;color:#1f2937}.header{margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:8px}.title{font-size:18px;font-weight:700}.sub{color:#6b7280}.message{border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:10px}.meta{display:flex;justify-content:space-between;font-size:11px;color:#6b7280;margin-bottom:6px}.content{white-space:pre-wrap}.attachment{margin-top:6px;font-size:11px;color:#374151}</style></head><body><div class="header"><div class="title">Conversation</div><div class="sub">Patient : ' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . ' | Médecin : ' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . '</div><div class="sub">Sujet : ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</div></div>' . $rows . '</body></html>';

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
                'Content-Disposition' => 'attachment; filename="conversation-' . $conversation->getId() . '.pdf"',
            ]
        );
    }

    #[Route('/conversation/create', name: 'conversation_create', methods: ['POST'])]
    public function createConversation(Request $request, EntityManagerInterface $em, UserRepository $repo, ConversationRepository $convRepo): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $message = new Message();
        $message->setSender($user);

        $form = $this->createForm(MessageType::class, $message, [
            'is_new_conversation' => true,
            'available_admins' => $repo->findAdmins(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $medecinId = $form->get('medecin_id')->getData();
            $medecin = $repo->find($medecinId);

            // VÃ©rifier si conversation existe dÃ©jÃ 
            $conversation = $convRepo->findBetweenUsers($user, $medecin);

            if (!$conversation) {
                $conversation = new Conversation();
                $conversation->setMedecin($medecin);
                $conversation->setUser($user);
                $conversation->setType($form->get('conversation_type')->getData());
                $conversation->setSujet($form->get('sujet')->getData());
                $em->persist($conversation);
            }

            $message->setConversation($conversation);

            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('front_messenger_show', ['id' => $conversation->getId()]);
        }



        // En cas d'erreur, on rÃ©affiche la page index avec le formulaire et les erreurs
        // RÃ©cupÃ©ration des donnÃ©es nÃ©cessaires pour la vue index (copiÃ© de index())
        $conversations = $convRepo->findByUser($user);

        // Tri par dÃ©faut
        usort($conversations, function($a, $b) {
            return $b->getUpdatedAt() <=> $a->getUpdatedAt();
        });

        return $this->render('messenger/front_index.html.twig', [
            'conversations' => $conversations,
            'form' => $form->createView(),
            'search' => null,
            'sortBy' => 'updatedAt',
            'sortOrder' => 'DESC',
            'viewer' => $user,
        ]);
    }

    #[Route('/conversation/{id}/message', name: 'message_add', methods: ['POST'])]
    public function addMessage(int $id, Request $request, ConversationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $conversation = $repo->find($id);
        if (!$conversation) return $this->redirectToRoute('front_messenger_index');

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);

        $form = $this->createForm(MessageType::class, $message, ['is_new_conversation' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // GESTION DE L'UPLOAD DE FICHIER
            $attachmentFile = $form->get('attachment')->getData();

            if ($attachmentFile) {
                $originalFilename = pathinfo($attachmentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $this->slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $attachmentFile->guessExtension();
                $uploadDir        = $this->getParameter('kernel.project_dir') . '/public/uploads/attachments';

                try {
                    $attachmentFile->move($uploadDir, $newFilename);
                    $message->setAttachment($newFilename);

                    // ─── ANALYSE OCR (uniquement côté patient) ───────────────────
                    // Lancer l'analyse en arrière-plan après la sauvegarde
                    $ocrResult = $this->documentAnalyzer->analyzeDocument($newFilename);
                    if ($ocrResult !== null) {
                        $message->setAiAnalysis($ocrResult);
                    }
                    // ─────────────────────────────────────────────────────────────

                } catch (FileException $e) {
                    // En cas d'erreur upload, continuer sans pièce jointe
                }
            }

            $em->persist($message);
            $conversation->touch();
            $em->flush();

            // --- NOTIFICATIONS EMAIL ---
            $type = strtolower(trim((string) $conversation->getType()));
            if ($type === 'urgence' || $type === 'urgentes' || $type === 'urgences' || str_contains($type, 'urgenc')) {
                $this->mailerService->sendEmergencyNotification($message);
            } else {
                $this->mailerService->sendNewMessageNotification($message, 'ROLE_ADMIN');
            }
            // ---------------------------

            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'message' => [
                        'id' => $message->getId(),
                        'content' => $this->messengerService->normalizeContent($message->getContent()),
                        'created_at' => $message->getCreatedAt()->format('c'),
                        'sender_id' => $message->getSender()->getId(),
                        'sender_role' => in_array('ROLE_ADMIN', $message->getSender()->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER',
                        'sender_name' => $message->getSender()->getUserIdentifier(),
                        'is_read' => $message->isRead(),
                        'attachment' => $message->getAttachment(),
                    ],
                    'last_update' => $conversation->getUpdatedAt()->getTimestamp()
                ]);
            }
        }

        return $this->redirectToRoute('front_messenger_show', ['id' => $id]);
    }

    #[Route('/message/{id}/edit', name: 'message_edit', methods: ['GET', 'POST'])]
    public function editMessage(int $id, Request $request, MessageRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $message = $repo->find($id);
        if (!$message || $message->getSender() !== $user) {
            return $this->redirectToRoute('front_messenger_index');
        }

        $form = $this->createForm(MessageType::class, $message, ['is_new_conversation' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->getConversation()->touch();
            $em->flush();
            return $this->redirectToRoute('front_messenger_show', ['id' => $message->getConversation()->getId()]);
        }

        return $this->render('messenger/edit_message.html.twig', [
            'message' => $message,
            'form' => $form->createView(),
            'viewer' => $user,
            'viewerType' => 'user',
        ]);
    }

    #[Route('/message/{id}/delete', name: 'message_delete', methods: ['GET'])]
    public function deleteMessage(int $id, MessageRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $message = $repo->find($id);
        if ($message && $message->getSender() === $user) {
            $message->getConversation()->touch();
            $em->remove($message);
            $em->flush();
        }

        return $this->redirectToRoute('front_messenger_show', ['id' => $message->getConversation()->getId()]);
    }

    // --- REAL TEMPS ROUTES ---

    #[Route('/check-new-messages/{id}', name: 'check_new_messages', methods: ['GET'])]
    public function checkNewMessages(int $id, Request $request, ConversationRepository $repo): JsonResponse
    {
        $lastUpdateStr = $request->query->get('last_update', '0');
        $conversation = $repo->find($id);

        if (!$conversation) {
            return new JsonResponse(['success' => false]);
        }

        if ($this->messengerService->shouldRefresh($conversation, (int)$lastUpdateStr)) {
            $messages = $conversation->getMessages()->toArray();
            $messages = $this->messengerService->sortMessages($messages);
            $messagesData = $this->messengerService->serializeMessages($messages);

            return new JsonResponse([
                'success' => true,
                'full_refresh' => true,
                'last_update' => $conversation->getUpdatedAt()->getTimestamp(),
                'messages' => $messagesData
            ]);
        }

        return new JsonResponse(['success' => true, 'full_refresh' => false]);
    }

    #[Route('/typing-status', name: 'typing_status', methods: ['POST'])]
    public function typingStatus(Request $request): JsonResponse
    {
        $conversationId = $request->request->get('conversation_id');
        $isTyping = $request->request->get('is_typing') === 'true';
        $user = $this->getUser();

        if ($conversationId && $user instanceof User) {
            $this->typingManager->setTypingStatus($conversationId, $user->getId(), $isTyping);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/check-typing/{id}', name: 'check_typing', methods: ['GET'])]
    public function checkTyping(int $id): JsonResponse
    {
        $user = $this->getUser();
        $isTyping = false;

        if ($user instanceof User) {
            $otherUsersTyping = $this->typingManager->getTypingUsers($id, $user->getId());
            $isTyping = count($otherUsersTyping) > 0;
        }

        return new JsonResponse(['typing' => $isTyping]);
    }

    #[Route('/check-global-status', name: 'check_global_status', methods: ['GET'])]
    public function checkGlobalStatus(ConversationRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse([]);

        $conversations = $repo->findByUser($user);
        $data = [];

        foreach ($conversations as $conv) {
            $lastMessage = $conv->getMessages()->last();
            $lastContent = $lastMessage ? (string) $this->messengerService->normalizeContent($lastMessage->getContent()) : '';
            $unreadCount = $conv->getUnreadCountFor($user);

            $data[] = [
                'id' => $conv->getId(),
                'last_message' => $lastContent !== '' ? mb_substr($lastContent, 0, 50) . '...' : 'Aucun message',
                'date' => $conv->getUpdatedAt()->format('d/m/Y H:i'),
                'unread_count' => $unreadCount,
                'status_label' => $unreadCount > 0 ? $unreadCount . ' non lu(s)' : 'Ã€ jour',
                'status_class' => $unreadCount > 0 ? 'bg-danger' : 'bg-success'
            ];
        }

        return new JsonResponse(['conversations' => $data]);
    }
}
