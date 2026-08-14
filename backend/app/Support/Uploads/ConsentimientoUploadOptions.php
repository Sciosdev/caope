<?php

namespace App\Support\Uploads;

use App\Models\Parametro;

final class ConsentimientoUploadOptions
{
    private const DEFAULT_MIMES = 'pdf,jpg,jpeg,png';

    private const DEFAULT_MAX_KB = 5120;

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public static function allowedExtensionsString(): string
    {
        $configured = (string) Parametro::obtener('uploads.consentimientos.mimes', self::DEFAULT_MIMES);
        $allowed = collect(explode(',', $configured))
            ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
            ->filter(fn (string $extension): bool => in_array($extension, self::ALLOWED_EXTENSIONS, true))
            ->unique()
            ->values();

        return $allowed->isEmpty() ? self::DEFAULT_MIMES : $allowed->implode(',');
    }

    public static function maxKilobytes(): int
    {
        $configured = (int) Parametro::obtener('uploads.consentimientos.max', self::DEFAULT_MAX_KB);

        return min(max($configured, 1), self::DEFAULT_MAX_KB);
    }

    public static function isSafeConfiguration(string $extensions): bool
    {
        $configured = collect(explode(',', $extensions))
            ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
            ->filter()
            ->unique()
            ->values();

        return $configured->isNotEmpty()
            && $configured->every(fn (string $extension): bool => in_array($extension, self::ALLOWED_EXTENSIONS, true));
    }
}
