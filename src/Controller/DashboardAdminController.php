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
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


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
    $sort   = $request->query->get('sort');

    $qb = $userRepository->createQueryBuilder('u');

    if ($search) {
        $qb->andWhere('u.email LIKE :search
                       OR u.username LIKE :search
                       OR u.telephone LIKE :search')
           ->setParameter('search', '%'.$search.'%');
    }

    if ($sort && in_array($sort, ['email','username','id'])) {
        $qb->orderBy('u.'.$sort, 'ASC');
    }

    $users = $qb->getQuery()->getResult();

    // =========================
    // 🔥 STATISTIQUES PAR ROLE
    // =========================

    $stats = [
        'total' => count($userRepository->findAll()),
        'admin' => count($userRepository->findByRole('ROLE_ADMIN')),
        'medecin' => count($userRepository->findByRole('ROLE_MEDECIN')),
        'nutritionniste' => count($userRepository->findByRole('ROLE_NUTRITIONNISTE')),
        'coach' => count($userRepository->findByRole('ROLE_COACHSPORTIF')),
        'patient' => count($userRepository->findByRole('ROLE_PATIENT')),
    ];

    if ($request->isXmlHttpRequest()) {
        return $this->render('backend/admin/_users_table.html.twig', [
            'users' => $users
        ]);
    }

    return $this->render('backend/admin/dashboardadmin.html.twig', [
        'users' => $users,
        'stats' => $stats
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

   #[Route('/admin/profile', name: 'admin_profile')]
public function profile(
    Request $request,
    EntityManagerInterface $entityManager,
    UserPasswordHasherInterface $passwordHasher
): Response {

    $user = $this->getUser();

    if (!$user instanceof User) {
        throw $this->createAccessDeniedException();
    }

    $form = $this->createForm(ProfileType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $plainPassword = $form->get('plainPassword')->getData();

        if (!empty($plainPassword)) {
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

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


#[Route('/admin/export/pdf', name: 'admin_export_pdf')]
public function exportPdf(UserRepository $userRepository): Response
{
    $users = $userRepository->findAll();

    $html = $this->renderView('backend/admin/pdf_users.html.twig', [
        'users' => $users,
    ]);

    $options = new Options();
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="users.pdf"',
        ]
    );
}

#[Route('/admin/user/{id}/ban', name: 'admin_user_ban', methods: ['POST'])]
public function toggleBan(
    User $user,
    EntityManagerInterface $em,
    Request $request
): Response {

    if (!$this->isCsrfTokenValid('ban'.$user->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    $user->setIsBanned(!$user->isBanned());
    $em->flush();

    return $this->redirectToRoute('admin_dashboard');
}


}
