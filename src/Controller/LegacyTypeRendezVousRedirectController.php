<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class LegacyTypeRendezVousRedirectController extends AbstractController
{
    #[Route('/admin/type-rendez-vous', name: 'legacy_admin_type_rendez_vous_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('admin_type_rendez_vous_index');
    }
}

