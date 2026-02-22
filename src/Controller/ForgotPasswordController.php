<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {

        if ($request->isMethod('POST')) {

            $emailInput = $request->request->get('email');
            $user = $userRepository->findOneBy(['email' => $emailInput]);

            if ($user) {

                // Générer mot de passe temporaire
                $temporaryPassword = substr(bin2hex(random_bytes(6)), 0, 10);

                // Hasher
                $hashed = $passwordHasher->hashPassword($user, $temporaryPassword);
                $user->setPassword($hashed);
                $em->flush();

                // Envoyer email
                $email = (new Email())
                    ->from('mhirifedi22@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->html("
                        <p>Bonjour,</p>
                        <p>Voici votre nouveau mot de passe temporaire :</p>
                        <h2>$temporaryPassword</h2>
                        <p>Merci de le modifier après connexion.</p>
                    ");

                $mailer->send($email);
            }

            $this->addFlash('success', 'Si un compte existe, un email a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }
}
