<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontUserController extends AbstractController
{
    #[Route('/Vue', name: 'app_frontend_vue')]
    public function index(): Response
    {
        return $this->render('frontend/index.html.twig');
    }

    #[Route('/Vue/LoginUser', name: 'frontend_login')]
    public function loginuser(): Response
    {
        return $this->render('frontend/login.html.twig');
    }

    #[Route('/Vue/RegistreUser', name: 'frontend_register')]
    public function register(): Response
    {
        return $this->render('frontend/register.html.twig', [
            'active_nav' => 'register'
        ]);
    }

    #[Route('/Vue/forgot-password', name: 'frontend_forgot_password')]
    public function forgetpassword(): Response
    {
        return $this->render('frontend/forgot_password.html.twig');
    }
}
