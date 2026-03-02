<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FrontProfileController extends AbstractController
{
    #[Route('/Vue/profile', name: 'frontend_profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 🔐 Récupérer mot de passe
            $plainPassword = $form->get('plainPassword')->getData();

            if (!empty($plainPassword)) {

                // Vérification complexité serveur
                if (
                    strlen($plainPassword) < 8 ||
                    !preg_match('/[A-Z]/', $plainPassword) ||
                    !preg_match('/[0-9]/', $plainPassword)
                ) {
                    $this->addFlash('danger', 
                        'Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre.'
                    );

                    return $this->redirectToRoute('frontend_profile');
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès.');

            return $this->redirectToRoute('frontend_profile');
        }

        return $this->render('frontend/profile.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }
}
