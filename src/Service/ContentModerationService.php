<?php

namespace App\Service;

class ContentModerationService
{
    /** @var string[] */
    private array $bannedWords;

    public function __construct(array $bannedWords = [])
    {
        $this->bannedWords = array_values(array_filter($bannedWords, fn($w) => is_string($w) && $w !== ''));
    }

    public function moderate(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        $result = $text;
        foreach ($this->bannedWords as $word) {
            $pattern = '/' . preg_quote($word, '/') . '/iu';
            $result = preg_replace_callback($pattern, function ($m) {
                $len = mb_strlen($m[0], 'UTF-8');
                return str_repeat('*', $len);
            }, $result);
        }
        return $result;
    }
}
