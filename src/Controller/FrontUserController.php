<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FrontUserController extends AbstractController
{
    #[Route('/Vue', name: 'app_frontend_vue')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_MEDECIN')) {
            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        return $this->render('frontend/index.html.twig');
    }

    // ===============================
    // 1️⃣ ENVOI DU CODE
    // ===============================
    #[Route('Vue/forgot-password', name: 'frontend_forgot_password')]
    public function Forgetpassword(
        Request $request,
        UserRepository $userRepository,
        MailerInterface $mailer
    ): Response
    {
        if ($request->isMethod('POST')) {

            $emailInput = trim($request->request->get('email'));

            if (empty($emailInput)) {
                $this->addFlash('danger', 'Veuillez saisir votre adresse email.');
                return $this->redirectToRoute('frontend_forgot_password');
            }

            $user = $userRepository->findOneBy(['email' => $emailInput]);

            if (!$user) {
                $this->addFlash('danger', 'Aucun compte trouvé avec cette adresse email.');
                return $this->redirectToRoute('frontend_forgot_password');
            }

            $code = random_int(100000, 999999);

            $session = $request->getSession();

            $session->set('reset_code', $code);
            $session->set('reset_email', $user->getEmail());
            $session->set('reset_expires_at', time() + 300); 
            $session->set('reset_attempts', 0); 

            $email = (new Email())
                ->from('mhirifedi22@gmail.com')
                ->to($user->getEmail())
                ->subject('Code de vérification')
                ->html("
                    <h3>Réinitialisation de mot de passe</h3>
                    <p>Voici votre code de vérification :</p>
                    <h2>$code</h2>
                    <p>Ce code expire dans 30 secondes.</p>
                ");

            $mailer->send($email);

            return $this->redirectToRoute('frontend_verify_code');
        }

        return $this->render('frontend/forgot_password.html.twig');
    }

    // ===============================
    // 2️⃣ VERIFICATION CODE
    // ===============================
    #[Route('Vue/verify-code', name: 'frontend_verify_code')]
    public function verifyCode(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->isMethod('POST')) {

            $inputCode = $request->request->get('code');
            $storedCode = $session->get('reset_code');
            $expiresAt = $session->get('reset_expires_at');
            $attempts = $session->get('reset_attempts', 0);

            // 🔴 Vérifier expiration
            if (!$expiresAt || time() > $expiresAt) {

                $session->remove('reset_code');
                $session->remove('reset_email');
                $session->remove('reset_expires_at');
                $session->remove('reset_attempts');

                $this->addFlash('danger', 'Le code a expiré. Veuillez recommencer.');
                return $this->redirectToRoute('frontend_forgot_password');
            }

            // 🔴 Vérifier tentatives
            if ($attempts >= 3) {

                $session->remove('reset_code');
                $session->remove('reset_email');
                $session->remove('reset_expires_at');
                $session->remove('reset_attempts');

                $this->addFlash('danger', 'Trop de tentatives. Veuillez recommencer.');
                return $this->redirectToRoute('frontend_forgot_password');
            }

            // 🔴 Code incorrect
            if ($inputCode != $storedCode) {

                $session->set('reset_attempts', $attempts + 1);

                $this->addFlash('danger', 'Code incorrect.');
                return $this->redirectToRoute('frontend_verify_code');
            }

            // ✅ Code valide
            return $this->redirectToRoute('frontend_new_password');
        }

        return $this->render('frontend/verify_code.html.twig');
    }

    // ===============================
    // 3️⃣ FORMULAIRE NOUVEAU MDP
    // ===============================
    #[Route('Vue/new-password', name: 'frontend_new_password')]
    public function newPassword(): Response
    {
        return $this->render('frontend/new_password.html.twig');
    }

    // ===============================
    // 4️⃣ SAUVEGARDE NOUVEAU MDP
    // ===============================
    #[Route('Vue/save-new-password', name: 'frontend_save_new_password', methods:['POST'])]
    public function saveNewPassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response
    {
        $session = $request->getSession();

        $email = $session->get('reset_email');

        if (!$email) {
            return $this->redirectToRoute('frontend_forgot_password');
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->redirectToRoute('frontend_forgot_password');
        }

        $password = trim($request->request->get('password'));
        $confirmPassword = trim($request->request->get('confirm_password'));

        if (empty($password) || empty($confirmPassword)) {
            $this->addFlash('danger', 'Tous les champs sont obligatoires.');
            return $this->redirectToRoute('frontend_new_password');
        }

        if ($password !== $confirmPassword) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('frontend_new_password');
        }

        if (strlen($password) < 8) {
            $this->addFlash('danger', 'Minimum 8 caractères.');
            return $this->redirectToRoute('frontend_new_password');
        }

        if (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            $this->addFlash('danger', 'Doit contenir au moins une majuscule et un chiffre.');
            return $this->redirectToRoute('frontend_new_password');
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $em->flush();

        // 🔐 Nettoyage session
        $session->remove('reset_code');
        $session->remove('reset_email');
        $session->remove('reset_expires_at');
        $session->remove('reset_attempts');

        $this->addFlash('success', 'Mot de passe modifié avec succès.');

        return $this->redirectToRoute('app_login');
    }
}
