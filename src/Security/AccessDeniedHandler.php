<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
final class AccessDeniedHandler implements \Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        if ($request->isXmlHttpRequest()
            || str_starts_with($request->getPathInfo(), '/api')
            || str_contains($request->headers->get('Accept', ''), 'application/json')
        ) {
            return new JsonResponse(
                ['error' => 'Access denied', 'message' => 'Vous n\'avez pas les droits pour accéder à cette ressource.'],
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'application/json']
            );
        }

        try {
            return new RedirectResponse($this->urlGenerator->generate('app_frontend_vue'));
        } catch (\Throwable) {
            return new Response('Access denied.', Response::HTTP_FORBIDDEN);
        }
    }
}
