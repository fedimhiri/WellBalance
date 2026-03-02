<?php

namespace App\Service;

class CryptoService
{
    private string $keyMaterial;
    private bool $enabled;

    public function __construct(string $encryptionKey = '', bool $encryptionEnabled = false)
    {
        $this->keyMaterial = $encryptionKey ?? '';
        $this->enabled = $encryptionEnabled && $this->keyMaterial !== '';
    }

    private function getKey(): string
    {
        return hash('sha256', $this->keyMaterial, true);
    }

    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        if (!$this->enabled) {
            return $plaintext;
        }
        $key = $this->getKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }
        $parts = [
            'v1',
            'gcm',
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($ciphertext),
        ];
        return implode(':', $parts);
    }

    public function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return $cipher;
        }
        if (!$this->enabled || !str_starts_with($cipher, 'v1:gcm:')) {
            return $cipher;
        }
        $parts = explode(':', $cipher);
        if (count($parts) !== 5) {
            return $cipher;
        }
        [$version, $mode, $ivB64, $tagB64, $ctB64] = $parts;
        $iv = base64_decode($ivB64, true);
        $tag = base64_decode($tagB64, true);
        $ct = base64_decode($ctB64, true);
        if ($iv === false || $tag === false || $ct === false) {
            return $cipher;
        }
        $key = $this->getKey();
        $plaintext = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            return $cipher;
        }
        return $plaintext;
    }
}
