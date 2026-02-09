<?php

namespace App\Security;

use App\Entity\Document;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const DOCUMENT_OWNER = 'DOCUMENT_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::DOCUMENT_OWNER && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Document $document */
        $document = $subject;
        $user = $token->getUser();

        if (!$user) {
            return false;
        }

        // Admin can access any document
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        // User can access only their own documents
        return $document->getUser() && $document->getUser()->getId() === $user->getId();
    }
}
