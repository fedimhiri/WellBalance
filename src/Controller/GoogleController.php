<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;




use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Response;
class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry)
    {
        return $clientRegistry
    ->getClient('google')
    ->redirect(
        ['email', 'profile'],
        [
            'prompt' => 'select_account',
        ]
    );

    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck()
    {
        // handled by authenticator
    }

     #[Route('/test-mail', name: 'app_test_mail')]
    public function testMail(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from('mhirifedi22@gmail.com')
                ->to('fedi.mhiri@esprit.tn')
                ->subject('Test SMTP Gmail')
                ->text('Ça fonctionne !');

            $mailer->send($email);

            return new Response('Email envoyé avec succès !');
        } catch (\Exception $e) {
            return new Response('Erreur : ' . $e->getMessage());
        }
    }
}
