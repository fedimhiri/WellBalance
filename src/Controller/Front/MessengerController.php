<?php

namespace App\Controller\Front;

use App\Entity\Message;
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
use App\Service\TypingManager;
use App\Service\MessengerService;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/Vue/messenger', name: 'front_messenger_')]
class MessengerController extends AbstractController
{
    private $messengerService;
    private $typingManager;

    public function __construct(MessengerService $messengerService, TypingManager $typingManager)
    {
        $this->messengerService = $messengerService;
        $this->typingManager = $typingManager;
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

        // Récupérer les conversations
        $conversations = $conversationRepo->findByUser($user);

        // Filtre de recherche simple
        $search = $request->query->get('search');
        if ($search) {
            $conversations = array_filter($conversations, function($c) use ($search) {
                foreach ($c->getMessages() as $m) {
                    if (str_contains(strtolower($m->getContent()), strtolower($search))) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Tri
        $sortBy = $request->query->get('sortBy', 'updatedAt');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        usort($conversations, function($a, $b) use ($sortBy, $sortOrder) {
            $valA = $sortBy === 'updatedAt' ? $a->getUpdatedAt() : $a->getCreatedAt();
            $valB = $sortBy === 'updatedAt' ? $b->getUpdatedAt() : $b->getCreatedAt();
            
            if ($sortBy === 'type') {
                $valA = $a->getType();
                $valB = $b->getType();
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
        
        // Vérification simple
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

        // Récupérer et trier les messages
        $messages = $conversation->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        // Recherche dans les messages
        $search = $request->query->get('searchMsg');
        if ($search) {
            $messages = array_filter($messages, fn($m) => str_contains(strtolower($m->getContent()), strtolower($search)));
        }

        // Formulaire
        $form = $this->createForm(MessageType::class, new Message(), ['is_new_conversation' => false]);

        // Navigation (Précédent / Suivant)
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
            'prevConv' => $prevConversation,
            'nextConv' => $nextConversation,
        ]);
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
            $doctorId = $form->get('doctor_id')->getData();
            $doctor = $repo->find($doctorId);
            
            // Vérifier si conversation existe déjà
            $conversation = $convRepo->findBetweenUsers($user, $doctor);
            
            if (!$conversation) {
                $conversation = new Conversation();
                $conversation->setDoctor($doctor);
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
        
        return $this->redirectToRoute('front_messenger_index');
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
            $em->persist($message);
            $conversation->touch();
            $em->flush();

            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept'), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'message' => [
                        'id' => $message->getId(),
                        'content' => $message->getContent(),
                        'created_at' => $message->getCreatedAt()->format('c'),
                        'sender_id' => $message->getSender()->getId(),
                        'sender_role' => in_array('ROLE_ADMIN', $message->getSender()->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER',
                        'is_read' => $message->isRead(),
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

        if ($conversationId && $user) {
            $this->typingManager->setTypingStatus($conversationId, $user->getId(), $isTyping);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/check-typing/{id}', name: 'check_typing', methods: ['GET'])]
    public function checkTyping(int $id): JsonResponse
    {
        $user = $this->getUser();
        $isTyping = false;

        if ($user) {
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
            $unreadCount = $conv->getUnreadCountFor($user);
            
            $data[] = [
                'id' => $conv->getId(),
                'last_message' => $lastMessage ? substr($lastMessage->getContent(), 0, 50) . '...' : 'Aucun message',
                'date' => $conv->getUpdatedAt()->format('d/m/Y H:i'),
                'unread_count' => $unreadCount,
                'status_label' => $unreadCount > 0 ? $unreadCount . ' non lu(s)' : 'À jour',
                'status_class' => $unreadCount > 0 ? 'bg-danger' : 'bg-success'
            ];
        }

        return new JsonResponse(['conversations' => $data]);
    }
}