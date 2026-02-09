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
use App\Repository\ObjectifSportifRepository;
use App\Repository\ActivitePhysiqueRepository;

final class DashboardAdminController extends AbstractController
{
    /* ============================
     *  DASHBOARD : LISTE + SEARCH
     * ============================ */
    #[Route('/admin_dashboard', name: 'admin_dashboard')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        ObjectifSportifRepository $objectifRepo,
        ActivitePhysiqueRepository $activiteRepo
    ): Response {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        // Méthode custom dans le repository
        $users = $userRepository->searchAndSort($search, $sort);

        // Stats Logic
        $objectifs = $objectifRepo->findAll();
        $activites = $activiteRepo->findAll();
        $totalObjectifs = count($objectifs);
        $totalActivites = count($activites);
        $objectifsAtteints = count(array_filter($objectifs, fn($o) => $o->getStatut() === 'Atteint'));
        $completionRate = $totalObjectifs > 0 ? round(($objectifsAtteints / $totalObjectifs) * 100) : 0;
        $totalCalories = 0;
        foreach ($activites as $activite) {
            $totalCalories += $activite->getCaloriesEstimees();
        }

        return $this->render('backend/admin/dashboardadmin.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'stats' => [
                'totalObjectifs' => $totalObjectifs,
                'totalActivites' => $totalActivites,
                'completionRate' => $completionRate,
                'totalCalories' => $totalCalories
            ]
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

    #[Route('/admin/profile', name: 'admin_profile1')]
public function profile(
    Request $request,
    EntityManagerInterface $entityManager
): Response {
    $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException();
    }

    $form = $this->createForm(ProfileType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();

        $this->addFlash('success', 'Profil mis à jour avec succès.');

        return $this->redirectToRoute('admin_profile1');
    }

    return $this->render('backend/admin/profile.html.twig', [
        'profileForm' => $form->createView(), // ✅ OBLIGATOIRE
    ]);
}


    /* ============================
     *  LOGIN / LOGOUT ADMIN
     * ============================ */
    #[Route('/seconnecter', name: 'app_admin_login')]
    public function login(): Response
    {
        return $this->render('backend/admin/login.html.twig');
    }


}
