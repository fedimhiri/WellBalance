<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $referer = $request->headers->get('referer');

        if ($referer && strpos($referer, '/admin') !== false) {
            $response = new RedirectResponse($this->urlGenerator->generate('app_admin_login'));
        } else {
            $response = new RedirectResponse($this->urlGenerator->generate('app_frontend_vue'));
        }

        $event->setResponse($response);
    }
}
