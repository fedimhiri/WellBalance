<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InsuranceApiService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{status: string, reference: string}
     */
    /**
     * @return array{status: string, reference: string}
     */
    public function submit(Document $document): array
    {
        $accepted = random_int(1, 100) <= 80;

        if ($accepted) {
            $year = date('Y');
            $random = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $reference = sprintf('INS-%s-%s', $year, $random);

            $document->setInsuranceReference($reference);
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Insurance submission simulated (accepted)', [
                'document_id' => $document->getId(),
                'reference' => $reference,
            ]);

            return [
                'status' => 'accepted',
                'reference' => $reference,
            ];
        }

        $this->logger->info('Insurance submission simulated (rejected)', [
            'document_id' => $document->getId(),
        ]);

        return [
            'status' => 'rejected',
            'reference' => '—',
        ];
    }
}
