<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class DocumentUploadService
{
    private const UPLOAD_DIR = 'uploads/documents';
    private const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_MIME = ['application/pdf'];

    public function __construct(
        private readonly string $projectDir,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $this->slugger->slug($originalFilename)->toString()) ?: 'document';
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.pdf';

        $uploadPath = $this->projectDir . '/public/' . self::UPLOAD_DIR;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0775, true);
        }

        $file->move($uploadPath, $newFilename);

        return self::UPLOAD_DIR . '/' . $newFilename;
    }

    public function validate(UploadedFile $file): bool
    {
        if ($file->getSize() > self::MAX_SIZE) {
            return false;
        }
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            return false;
        }
        return true;
    }
}
