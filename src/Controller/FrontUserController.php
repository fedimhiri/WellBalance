<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontUserController extends AbstractController
{
    public function __construct(
        private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $userPasswordHasher,
        private \Doctrine\ORM\EntityManagerInterface $entityManager
    ) {}

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
    public function register(Request $request): Response
    {
        $user = new \App\Entity\User();
        // Manually handle the form data since we might not have the FormType set up for this specific view structure
        // OR better, use the existing RegistrationFormType if compatible.
        // Let's check if we can reuse RegistrationFormType or if we should map manual request data.
        // Given the template uses manual input names (firstname, lastname, email...), we should probably manual map or creating a form type.
        // The template `frontend/register.html.twig` does NOT use `form(...)` helper, it uses raw HTML inputs.
        // So we must handle Request manually.
        
        if ($request->isMethod('POST')) {
            $user->setEmail($request->request->get('email'));
            $user->setNom($request->request->get('lastname') . ' ' . $request->request->get('firstname'));
            $user->setTelephone($request->request->get('phone'));
            $user->setUsername($request->request->get('email')); // Use email as username default or ask user? Entity requires username.
            // Template doesn't have username field, so we might need to generate one or use email.

            $plainPassword = $request->request->get('password');
            $user->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword
                )
            );
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('frontend_login');
        }

        return $this->render('frontend/register.html.twig', [
            'active_nav' => 'register'
        ]);
    }

   
#[Route('Vue/forgot-password', name: 'frontend_forgot_password')]
 public function Forgetpassword(): Response
    {
        return $this->render('frontend/forgot_password.html.twig');
    }
}
