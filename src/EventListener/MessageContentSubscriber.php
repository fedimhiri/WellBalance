<?php

namespace App\EventListener;

use App\Entity\Message;
use App\Service\ContentModerationService;
use App\Service\CryptoService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class MessageContentSubscriber implements EventSubscriber
{
    private CryptoService $crypto;
    private ContentModerationService $moderation;

    public function __construct(CryptoService $crypto, ContentModerationService $moderation)
    {
        $this->crypto = $crypto;
        $this->moderation = $moderation;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::postPersist,
            Events::postUpdate,
            Events::postLoad,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Message) {
            return;
        }
        $content = $entity->getContent();
        if ($content !== null && $content !== '') {
            if (!str_starts_with($content, 'v1:gcm:')) {
                $filtered = $this->moderation->moderate($content);
                $entity->setContent($this->crypto->encrypt($filtered));
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Message) {
            return;
        }
        $content = $entity->getContent();
        if ($content !== null && $content !== '') {
            if (str_starts_with($content, 'v1:gcm:')) {
                $content = $this->crypto->decrypt($content);
            }
            $filtered = $this->moderation->moderate($content);
            $entity->setContent($this->crypto->encrypt($filtered));
            $em = $args->getEntityManager();
            $uow = $em->getUnitOfWork();
            $meta = $em->getClassMetadata(Message::class);
            $uow->recomputeSingleEntityChangeSet($meta, $entity);
        }
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Message) {
            return;
        }
        $content = $entity->getContent();
        if ($content !== null && $content !== '') {
            $entity->setContent($this->crypto->decrypt($content));
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Message) {
            return;
        }
        $content = $entity->getContent();
        if ($content !== null && $content !== '') {
            $entity->setContent($this->crypto->decrypt($content));
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Message) {
            return;
        }
        $content = $entity->getContent();
        if ($content !== null && $content !== '') {
            $entity->setContent($this->crypto->decrypt($content));
        }
    }
}
