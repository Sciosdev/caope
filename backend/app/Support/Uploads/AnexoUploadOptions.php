<?php

namespace App\Support\Uploads;

use App\Models\Parametro;

class AnexoUploadOptions
{
    private const DEFAULT_MIMES = 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt,csv';

    private const DEFAULT_MAX_KB = 51200;

    /**
     * @var array<string, string>
     */
    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
    ];

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        $rawExtensions = (string) Parametro::obtener('uploads.anexos.mimes', self::DEFAULT_MIMES);

        $allowed = collect(explode(',', $rawExtensions))
            ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
            ->filter(fn (string $extension): bool => array_key_exists($extension, self::MIME_BY_EXTENSION))
            ->unique()
            ->values();

        return $allowed->isEmpty()
            ? explode(',', self::DEFAULT_MIMES)
            : $allowed->all();
    }

    public static function isSafeConfiguration(string $extensions): bool
    {
        $configured = collect(explode(',', $extensions))
            ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
            ->filter()
            ->unique()
            ->values();

        return $configured->isNotEmpty()
            && $configured->every(fn (string $extension): bool => array_key_exists($extension, self::MIME_BY_EXTENSION));
    }

    public static function allowedExtensionsString(): string
    {
        return implode(',', self::allowedExtensions());
    }

    public static function maxKilobytes(): int
    {
        $max = (int) Parametro::obtener('uploads.anexos.max', self::DEFAULT_MAX_KB);

        return min(max($max, 1), self::DEFAULT_MAX_KB);
    }

    /**
     * @return list<string>
     */
    public static function acceptedTypes(): array
    {
        return collect(self::allowedExtensions())
            ->flatMap(function ($extension) {
                $normalized = '.'.$extension;
                $mimeType = self::MIME_BY_EXTENSION[$extension] ?? null;

                return $mimeType ? [$normalized, $mimeType] : [$normalized];
            })
            ->unique()
            ->values()
            ->all();
    }

    public static function acceptedTypesString(): string
    {
        return implode(',', self::acceptedTypes());
    }
}
