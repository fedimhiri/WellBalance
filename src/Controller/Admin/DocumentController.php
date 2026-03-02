<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentAdminType;
use App\Repository\DocumentRepository;
use App\Service\DocumentUploadService;
use App\Service\EmailService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document')]
#[IsGranted('ROLE_ADMIN')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentUploadService $uploadService,
        private readonly ?SmsService $smsService = null,
        private readonly ?EmailService $emailService = null,
    ) {
    }

    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;

        $documents = $this->documentRepository->findPaginated($page, $limit, null);
        $total = $this->documentRepository->countPaginated(null);
        $totalPages = (int) ceil($total / $limit);

        return $this->render('admin/document/index.html.twig', [
            'documents' => $documents,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentAdminType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();
            if (null !== $file) {
                $document->setCheminFichier($this->uploadService->upload($file));
            }
            $document->setDateUpload(new \DateTimeImmutable());
            $document->setUser($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Document créé avec succès.');

            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/new.html.twig', [
            'form' => $form,
            'document' => $document,
        ]);
    }

    #[Route('/{id}', name: 'admin_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('admin/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document): Response
    {
        $form = $this->createForm(DocumentAdminType::class, $document, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();
            if (null !== $file) {
                $document->setCheminFichier($this->uploadService->upload($file));
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Document mis à jour.');

            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/edit.html.twig', [
            'form' => $form,
            'document' => $document,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        if (!$this->isCsrfTokenValid('delete_document_' . $document->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_index');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document supprimé.');

        return $this->redirectToRoute('admin_document_index');
    }

    #[Route('/{id}/download', name: 'admin_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($document->getCheminFichier()));

        return $response;
    }

    #[Route('/{id}/send-sms', name: 'admin_document_send_sms', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendSms(Request $request, Document $document): Response
    {
        if (!$this->isCsrfTokenValid('send_sms_' . $document->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $user = $document->getUser();
        if (null === $user) {
            $this->addFlash('danger', 'Aucun utilisateur associé à ce document.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $phone = $user->getTelephone();
        if (null === $phone || empty($phone)) {
            $this->addFlash('danger', 'Aucun numéro de téléphone enregistré pour cet utilisateur.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        if (null === $this->smsService) {
            $this->addFlash('danger', 'Service SMS non configuré.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $message = sprintf(
            '🏥 WellBalance: Nouveau document disponible - %s (%s). Connectez-vous pour le consulter.',
            $document->getTitre(),
            $document->getTypeDocument()
        );

        $success = $this->smsService->send($phone, $message);

        if ($success) {
            $this->addFlash('success', 'SMS de notification envoyé à ' . $user->getDisplayName() . '.');
        } else {
            $this->addFlash('danger', 'Échec de l\'envoi du SMS. Veuillez vérifier la configuration.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/send-email', name: 'admin_document_send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(Request $request, Document $document): Response
    {
        if (!$this->isCsrfTokenValid('send_email_' . $document->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $user = $document->getUser();
        if (null === $user) {
            $this->addFlash('danger', 'Aucun utilisateur associé à ce document.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $email = $user->getEmail();
        if (null === $email || empty($email)) {
            $this->addFlash('danger', 'Aucune adresse email enregistrée pour cet utilisateur.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        if (null === $this->emailService) {
            $this->addFlash('danger', 'Service email non configuré.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $success = $this->emailService->sendDocument($email, $document, $projectDir);

        if ($success) {
            $this->addFlash('success', 'Email de notification envoyé à ' . $user->getDisplayName() . '.');
        } else {
            $this->addFlash('danger', 'Échec de l\'envoi de l\'email. Veuillez vérifier la configuration.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }
}
