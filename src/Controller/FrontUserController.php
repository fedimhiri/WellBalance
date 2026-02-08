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
    

     

   
#[Route('Vue/forgot-password', name: 'frontend_forgot_password')]
 public function Forgetpassword(): Response
    {
        return $this->render('frontend/forgot_password.html.twig');
    }
}
