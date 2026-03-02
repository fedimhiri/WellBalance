<?php

namespace App\Controller\Admin;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\Conversation;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\TypingManager;
use App\Service\MessengerService;
use App\Service\JitsiService;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Service\MailerService;

#[Route('/admin/messenger', name: 'admin_messenger_')]
class MessengerController extends AbstractController
{
    private $messengerService;
    private $typingManager;
    private $slugger;
    private JitsiService $jitsi;
    private $mailerService;

    public function __construct(MessengerService $messengerService, TypingManager $typingManager, SluggerInterface $slugger, JitsiService $jitsi, MailerService $mailerService)
    {
        $this->messengerService = $messengerService;
        $this->typingManager = $typingManager;
        $this->slugger = $slugger;
        $this->jitsi = $jitsi;
        $this->mailerService = $mailerService;
    }
    #[Route('/listes_conversations', name: 'index', methods: ['GET'])]
    public function index(Request $request, ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }

        // VÃ©rification Role
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $conversations = $conversationRepo->findByMedecin($user);

        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $conversations = array_filter($conversations, function($c) use ($needle, $user) {
                $parts = [];
                if ($c->getUser()) {
                    $parts[] = $c->getUser()?->getUserIdentifier();
                }
                if ($c->getMedecin()) {
                    $parts[] = $c->getMedecin()?->getUserIdentifier();
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

        $allowedSortBy = ['updatedAt', 'createdAt', 'type', 'user'];
        $sortBy = (string) $request->query->get('sortBy', 'updatedAt');
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'updatedAt';
        }

        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC'));
        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        usort($conversations, function($a, $b) use ($sortBy, $sortOrder) {
            $valA = $sortBy === 'createdAt' ? $a->getCreatedAt() : $a->getUpdatedAt();
            $valB = $sortBy === 'createdAt' ? $b->getCreatedAt() : $b->getUpdatedAt();

            if ($sortBy === 'type') {
                $valA = $a->getType() ?? '';
                $valB = $b->getType() ?? '';
            }
            if ($sortBy === 'user') {
                $valA = $a->getUser() ? $a->getUser()->getUserIdentifier() : '';
                $valB = $b->getUser() ? $b->getUser()->getUserIdentifier() : '';
            }

            if ($sortOrder === 'DESC') {
                return $valB <=> $valA;
            }
            return $valA <=> $valB;
        });

        $isAjax = $request->query->getBoolean('ajax')
            || $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');

        if ($isAjax) {
            $data = [];
            foreach ($conversations as $conv) {
                $lastMessage = $conv->getLastMessage();
                $lastContent = $lastMessage ? (string) $this->messengerService->normalizeContent($lastMessage->getContent()) : '';
                $short = $lastContent !== '' ? mb_substr($lastContent, 0, 50) . '...' : '';
                $unreadCount = $conv->getUnreadCountFor($user);

                $data[] = [
                    'id' => $conv->getId(),
                    'user_name' => $conv->getUser()?->getUserIdentifier() ?? '',
                    'user_id' => $conv->getUser() ? $conv->getUser()->getId() : null,
                    'last_message' => $short,
                    'has_message' => $lastContent !== '',
                    'status_label' => $unreadCount > 0 ? $unreadCount . ' non lu(s)' : 'À jour',
                    'status_class' => $unreadCount > 0 ? 'bg-danger' : 'bg-success',
                    'date' => $conv->getUpdatedAt() ? $conv->getUpdatedAt()->format('d/m/Y H:i') : '',
                    'show_url' => $this->generateUrl('admin_messenger_show', ['id' => $conv->getId()]),
                    'delete_url' => $this->generateUrl('admin_messenger_conversation_delete', ['id' => $conv->getId()]),
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

        return $this->render('messenger/index.html.twig', [
            'conversations' => $conversations,
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
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $conversation = $conversationRepo->find($id);

        if (!$conversation || $conversation->getMedecin() !== $user) {
            return $this->redirectToRoute('admin_messenger_index');
        }

        // Marquer comme lus
        foreach ($conversation->getMessages() as $msg) {
            if ($msg->getSender() !== $user && !$msg->isRead()) {
                $msg->setRead(true);
            }
        }
        $em->flush();
        
        // Messages triÃ©s
        $messages = $conversation->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        // Recherche simple
        $searchMsg = trim((string) $request->query->get('searchMsg', ''));
        if ($searchMsg !== '') {
            $needle = mb_strtolower($searchMsg);
            $messages = array_filter($messages, fn($m) => str_contains(mb_strtolower((string) $this->messengerService->normalizeContent($m->getContent())), $needle));
        }
        $messages = array_values($messages);
        foreach ($messages as $message) {
            $message->setContent($this->messengerService->normalizeContent($message->getContent()));
        }

        $form = $this->createForm(MessageType::class, new Message(), ['is_new_conversation' => false]);

        // Navigation (PrÃ©cÃ©dent / Suivant) pour Admin
        /** @var \App\Entity\User $user */
        $allConversations = $conversationRepo->findByMedecin($user);
        
        // Tri des conversations pour la cohÃ©rence
        usort($allConversations, function($a, $b) {
            return $b->getUpdatedAt() <=> $a->getUpdatedAt();
        });

        $currentIndex = array_search($conversation, $allConversations);
        $prevConversation = $allConversations[$currentIndex - 1] ?? null;
        $nextConversation = $allConversations[$currentIndex + 1] ?? null;

        return $this->render('messenger/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'form' => $form->createView(),
            'viewer' => $user,
            'viewerType' => 'admin',
            'searchMsg' => $searchMsg,
            'prevConv' => $prevConversation,
            'nextConv' => $nextConversation,
            'jitsiRoom' => $this->jitsi->getRoomName($conversation),
            'jitsiDomain' => $this->jitsi->getDomain(),
        ]);
    }

    #[Route('/conversation/{id}/message', name: 'message_add', methods: ['POST'])]
    public function addMessage(int $id, Request $request, ConversationRepository $conversationRepo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');

        $conversation = $conversationRepo->find($id);
        if (!$conversation) {
            return $this->redirectToRoute('admin_messenger_index');
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user instanceof \App\Entity\User ? $user : null);
        
        $form = $this->createForm(MessageType::class, $message, ['is_new_conversation' => false]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            // FILE UPLOAD HANDLING
            $attachmentFile = $form->get('attachment')->getData();

            if ($attachmentFile) {
                $originalFilename = pathinfo($attachmentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$attachmentFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/attachments';

                try {
                    $attachmentFile->move($uploadDir, $newFilename);
                    $message->setAttachment($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload du fichier.');
                }
            }

            $em->persist($message);
            $em->flush();
            $conversation->touch();
            $em->flush();

            // --- NOTIFICATIONS EMAIL ---
            $this->mailerService->sendNewMessageNotification($message, 'ROLE_USER');
            // ---------------------------
            
            // RETURN JSON FOR AJAX REQUESTS
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'message' => [
                        'id' => $message->getId(),
                        'content' => $this->messengerService->normalizeContent($message->getContent()),
                        'created_at' => $message->getCreatedAt()->format('c'),
                        'sender_id' => $message->getSender()->getId(),
                        'sender_role' => in_array('ROLE_ADMIN', $message->getSender()->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER',
                        'is_read' => $message->isRead(),
                        'attachment' => $message->getAttachment(),
                    ],
                    'last_update' => $conversation->getUpdatedAt()->getTimestamp()
                ]);
            }

            $this->addFlash('success', 'Message envoyÃ©.');
        }

        return $this->redirectToRoute('admin_messenger_show', ['id' => $id]);
    }

    #[Route('/conversation/{id}/meet', name: 'meet', methods: ['GET'])]
    public function meet(int $id, ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');
        $conversation = $conversationRepo->find($id);
        if (!$conversation || $conversation->getMedecin() !== $user) {
            return $this->redirectToRoute('admin_messenger_index');
        }
        $room = $this->jitsi->getRoomName($conversation);
        return $this->render('messenger/meet.html.twig', [
            'room' => $room,
            'domain' => $this->jitsi->getDomain(),
            'displayName' => $user->getUserIdentifier(),
            'base_template' => 'backend/baseend.html.twig',
        ]);
    }

    #[Route('/conversation/{id}/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(int $id, ConversationRepository $conversationRepo): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');
        $conversation = $conversationRepo->find($id);
        if (!$conversation || $conversation->getMedecin() !== $user) {
            return $this->redirectToRoute('admin_messenger_index');
        }

        $messages = $conversation->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $patientName = $conversation->getUser()?->getUserIdentifier() ?? '';
        $doctorName = $conversation->getMedecin()?->getUserIdentifier() ?? '';
        $subject = $conversation->getSujet() ?? 'Aucun sujet';

        $rows = '';
        foreach ($messages as $message) {
            $sender = $message->getSender()?->getUserIdentifier() ?? '';
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
    #[Route('/message/{id}/edit', name: 'message_edit', methods: ['GET', 'POST'])]
    public function editMessage(int $id, Request $request, MessageRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');

        $message = $repo->find($id);
        if (!$message || $message->getSender() !== $user) {
            return $this->redirectToRoute('admin_messenger_index');
        }

        $form = $this->createForm(MessageType::class, $message, ['is_new_conversation' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $message->getConversation()->touch();
            $em->flush();
            return $this->redirectToRoute('admin_messenger_show', ['id' => $message->getConversation()->getId()]);
        }

        return $this->render('messenger/edit_message.html.twig', [
            'message' => $message,
            'form' => $form->createView(),
            'viewer' => $user,
            'viewerType' => 'admin',
        ]);
    }

    #[Route('/message/{id}/delete', name: 'message_delete', methods: ['GET'])]
    public function deleteMessage(int $id, MessageRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');

        $message = $repo->find($id);
        if ($message && $message->getSender() === $user) {
            $message->getConversation()->touch();
            $em->remove($message);
            $em->flush();
        }
        
        return $this->redirectToRoute('admin_messenger_show', ['id' => $message->getConversation()->getId()]);
    }

    #[Route('/conversation/{id}/delete', name: 'conversation_delete', methods: ['GET'])]
    public function deleteConversation(int $id, ConversationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return $this->redirectToRoute('app_login');

        $conversation = $repo->find($id);
        if ($conversation && $conversation->getMedecin() === $user) {
            foreach ($conversation->getMessages() as $msg) {
            }
            $em->remove($conversation);
            $em->flush();
        }
        
        return $this->redirectToRoute('admin_messenger_index');
    }

    // Routes JSON simples pour le statut
    #[Route('/typing-status', name: 'typing_status', methods: ['POST'])]
    public function typingStatus(Request $request): JsonResponse
    {
        $conversationId = $request->request->get('conversation_id');
        $isTyping = $request->request->get('is_typing') === 'true';
        $user = $this->getUser();

        if ($conversationId && $user instanceof \App\Entity\User) {
            $this->typingManager->setTypingStatus($conversationId, $user->getId(), $isTyping);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/check-typing/{id}', name: 'check_typing', methods: ['GET'])]
    public function checkTyping(int $id): JsonResponse
    {
        $user = $this->getUser();
        $isTyping = false;

        if ($user instanceof \App\Entity\User) {
            $otherUsersTyping = $this->typingManager->getTypingUsers($id, $user->getId());
            $isTyping = count($otherUsersTyping) > 0;
        }

        return new JsonResponse(['typing' => $isTyping]);
    }

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

    #[Route('/check-global-status', name: 'check_global_status', methods: ['GET'])]
    public function checkGlobalStatus(ConversationRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) return new JsonResponse([]);

        /** @var \App\Entity\User $user */
        $conversations = $repo->findByMedecin($user);
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
