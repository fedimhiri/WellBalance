<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

class SessionIdleListener
{
    private int $maxIdleTime = 10; 

    public function __construct(
        private RouterInterface $router
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // Si pas connecté → on ne fait rien
        if (!$session->has('_security_main')) {
            return;
        }

        $lastUsed = $session->get('last_used');

        if ($lastUsed && (time() - $lastUsed > $this->maxIdleTime)) {

            $session->invalidate();

            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate('app_login')
                )
            );
            return;
        }

        $session->set('last_used', time());
    }
}
