<?php

namespace App\Services\Masking;

class NameMasker
{
    public static function mask(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $trimmed = trim($name);

        if ($trimmed === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return '';
        }

        $maskedWords = array_map(fn (string $word): string => self::maskWord($word), $words);

        return implode(' ', $maskedWords);
    }

    private static function maskWord(string $word): string
    {
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);

        if ($characters === false || count($characters) <= 1) {
            return $word;
        }

        $masked = '';

        foreach ($characters as $index => $char) {
            if ($index === 0) {
                $masked .= $char;
                continue;
            }

            $masked .= preg_match('/[\pL\pN]/u', $char) ? '*' : $char;
        }

        return $masked;
    }
}
