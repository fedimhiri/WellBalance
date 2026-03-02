<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\DocumentAnalyzerService;
use App\Service\EmailService;
use App\Service\InsuranceApiService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/documents', name: 'app_document_')]
#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentAnalyzerService $analyzerService,
        private readonly SmsService $smsService,
        private readonly EmailService $emailService,
        private readonly InsuranceApiService $insuranceApiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        $search = $request->query->get('search');
        $sortField = $request->query->get('sort', 'dateUpload');
        $direction = $request->query->get('direction', 'DESC');

        $documents = $this->documentRepository->searchAndSort(
            \is_string($search) ? $search : null,
            \is_string($sortField) ? $sortField : 'dateUpload',
            \is_string($direction) ? $direction : 'DESC',
            $user
        );

        return $this->render('document/list.html.twig', [
            'documents' => $documents,
            'search' => $search,
            'sort' => $sortField,
            'direction' => $direction,
        ]);
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        return $this->index($request);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyUnlessOwned($document);

        return $this->render('document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'], priority: 10)]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();

            if (null !== $fichier) {
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename)->toString();
                $safeFilename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $safeFilename) ?: 'document';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.pdf';

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                try {
                    $fichier->move($uploadDir, $newFilename);
                    $document->setCheminFichier('uploads/documents/' . $newFilename);
                } catch (\Exception $e) {
                    $this->logger->error('File upload failed', ['error' => $e->getMessage()]);
                    $this->addFlash('danger', 'Erreur lors de l\'upload du fichier.');
                    return $this->render('document/new.html.twig', ['form' => $form]);
                }
            }

            $user = $this->getUser();
            if (null === $user) {
                throw $this->createAccessDeniedException();
            }

            $document->setUser($user);
            $document->setDateUpload(new \DateTimeImmutable());

            $analysisText = $document->getTitre() . ' ' . $document->getTypeDocument();
            $analysis = $this->analyzerService->analyze($analysisText);
            $document->setResumeAi($analysis['resume']);
            $document->setMotsCles($analysis['mots_cles']);
            $document->setTypeDetecte($analysis['type_detecte']);

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            // Get notification preferences from form
            $notifySms = $form->get('notifySms')->getData() ?? true;
            $notifyEmail = $form->get('notifyEmail')->getData() ?? true;

            $phone = $user->getTelephone();
            
            // Send SMS notification if enabled
            if ($notifySms && null !== $phone && '' !== trim($phone)) {
                $to = str_starts_with($phone, '+') ? $phone : '+216' . $phone;
                $documentType = $document->getTypeDocument();
                $this->smsService->send($to, sprintf(
                    'WellBalance - Nouveau document: %s (Type: %s). Connectez-vous pour voir les details.',
                    $document->getTitre(),
                    $documentType
                ));
            }

            if (!empty($analysis['has_anomaly'])) {
                if (null !== $phone && '' !== trim($phone)) {
                    $to = str_starts_with($phone, '+') ? $phone : '+216' . $phone;
                    $this->smsService->send($to, sprintf(
                        "⚠️ WellBalance - Alerte médicale\n\n"
                        . "Document: \"%s\"\n\n"
                        . "🔔 Notre équipe médicale a détecté des éléments nécessitant une attention particulière:\n"
                        . "%s\n\n"
                        . "📞 Veuillez contacter votre médecin traitant pour plus d'informations.",
                        $document->getTitre(),
                        !empty($analysis['anomalies']) ? implode("\n- ", $analysis['anomalies']) : 'Veuillez consulter votre document'
                    ));
                }
            }

            // Send Email notification if enabled
            if ($notifyEmail && null !== $user->getEmail()) {
                $email = $user->getEmail();
                $projectDir = $this->getParameter('kernel.project_dir');
                $this->emailService->sendDocument(trim($email), $document, $projectDir);
            }

            $this->addFlash('success', 'Document ajouté et analysé avec succès.');

            return $this->redirectToRoute('app_document_index');
        }

        return $this->render('document/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        $this->denyUnlessOwned($document);
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('delete_document_' . $document->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_document_index');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document supprimé avec succès.');

        return $this->redirectToRoute('app_document_index');
    }

    #[Route('/{id}/export-pdf', name: 'export_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportPdf(Document $document): Response
    {
        $this->denyUnlessOwned($document);
        $html = $this->renderView('document/pdf_export.html.twig', [
            'document' => $document,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'document-' . $document->getId() . '-' . date('Y-m-d') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/{id}/send-sms', name: 'send_sms', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendSms(Request $request, Document $document): Response
    {
        $this->denyUnlessOwned($document);
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('sms_document_' . $document->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        $phone = $request->request->get('phone');
        if (!\is_string($phone) || '' === trim($phone)) {
            $this->addFlash('danger', 'Numéro de téléphone requis.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        $message = sprintf(
            'WellBalance - Document: %s | Type: %s | Résumé: %s',
            $document->getTitre(),
            $document->getTypeDocument(),
            mb_substr((string) $document->getResumeAi(), 0, 100)
        );

        $sent = $this->smsService->send(trim($phone), $message);

        if ($sent) {
            $this->addFlash('success', 'SMS envoyé avec succès.');
        } else {
            $this->addFlash('danger', 'Échec de l\'envoi du SMS.');
        }

        return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/send-email', name: 'send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(Request $request, Document $document): Response
    {
        $this->denyUnlessOwned($document);
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('email_document_' . $document->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        $email = $request->request->get('email');
        if (!\is_string($email) || '' === trim($email)) {
            $this->addFlash('danger', 'Adresse email requise.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $sent = $this->emailService->sendDocument(trim($email), $document, $projectDir);

        if ($sent) {
            $this->addFlash('success', 'Email envoyé avec succès.');
        } else {
            $this->addFlash('danger', 'Échec de l\'envoi de l\'email.');
        }

        return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/send-insurance', name: 'send_insurance', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendInsurance(Request $request, Document $document): Response
    {
        $this->denyUnlessOwned($document);
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('insurance_document_' . $document->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        try {
            $result = $this->insuranceApiService->submit($document);
        } catch (\Exception $e) {
            $this->logger->error('Insurance submission failed', ['error' => $e->getMessage()]);
            $this->addFlash('danger', 'Erreur lors de la soumission à l\'assurance.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        if ($result['status'] === 'accepted') {
            $user = $document->getUser();
            if (null !== $user) {
                $phone = $user->getTelephone();
                if (null !== $phone && '' !== trim($phone)) {
                    $to = str_starts_with($phone, '+') ? $phone : '+216' . $phone;
                    $this->smsService->send($to, sprintf(
                        "✅ WellBalance - Confirmation Assurance\n\n"
                        . "Document: \"%s\"\n"
                        . "Statut: ACCEPTÉ ✅\n"
                        . "Référence: %s\n\n"
                        . "📋 Votre document a été accepté par l'assurance.\n"
                        . "💡 Connectez-vous pour voir les détails complets.",
                        $document->getTitre(),
                        $result['reference']
                    ));
                }
            }
        }

        $this->addFlash($result['status'] === 'accepted' ? 'success' : 'warning', sprintf(
            'Document soumis à l\'assurance. Statut: %s | Référence: %s',
            $result['status'],
            $result['reference']
        ));

        return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
    }

    private function denyUnlessOwned(Document $document): void
    {
        $user = $this->getUser();
        if (null === $user || $document->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }
    }
}
