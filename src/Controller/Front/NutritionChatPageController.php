<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NutritionChatPageController extends AbstractController
{
    #[Route('/nutrition/chat', name: 'nutrition_chat_page', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('frontend/nutrition/chat.html.twig');
    }
}