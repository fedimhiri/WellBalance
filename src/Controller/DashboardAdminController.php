<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardAdminController extends AbstractController
{
    /* ============================
     *  DASHBOARD : LISTE + SEARCH
     * ============================ */
    #[Route('/admin_dashboard', name: 'admin_dashboard')]
    public function index(
        Request $request,
        UserRepository $userRepository
    ): Response {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        // Méthode custom dans le repository
        $users = $userRepository->searchAndSort($search, $sort);

        return $this->render('backend/admin/dashboardadmin.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    /* ============================
     *  DELETE USER
     * ============================ */
    #[Route('/admin/user/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(
        User $user,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    /* ============================
     *  EDIT USER (ADMIN)
     * ============================ */
    #[Route('/admin/user/{id}/edit', name: 'admin_user_edit')]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur modifié avec succès.');

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('backend/admin/edit_user.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    /* ============================
     *  PROFIL ADMIN
     * ============================ */
    #[Route('/admin/profile', name: 'admin_profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès.');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('backend/admin/profile.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    /* ============================
     *  LOGIN / LOGOUT ADMIN
     * ============================ */
    #[Route('/seconnecter', name: 'app_login')]
    public function login(): Response
    {
        return $this->render('backend/admin/login.html.twig');
    }

    #[Route('/sedeconnecter', name: 'app_logout_admin')]
    public function logout(): void
    {
        // Intercepté par Symfony Security
        throw new \LogicException('This method is intercepted by the firewall.');
    }
}
