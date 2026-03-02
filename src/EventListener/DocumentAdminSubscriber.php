<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Document;
use App\Service\DocumentAnalyzerService;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DocumentAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DocumentAnalyzerService $analyzerService,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => ['onBeforePersisted'],
        ];
    }

    public function onBeforePersisted(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof Document) {
            return;
        }

        $user = $this->security->getUser();
        if (null !== $user) {
            $entity->setUser($user);
        }

        if (null === $entity->getDateUpload()) {
            $entity->setDateUpload(new \DateTimeImmutable());
        }

        $analysisText = ($entity->getTitre() ?? '') . ' ' . ($entity->getTypeDocument() ?? '');
        if ('' !== trim($analysisText)) {
            $analysis = $this->analyzerService->analyze($analysisText);
            $entity->setResumeAi($analysis['resume']);
            $entity->setMotsCles($analysis['mots_cles']);
            $entity->setTypeDetecte($analysis['type_detecte']);
        }
    }
}
