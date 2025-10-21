<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $clave
 * @property string $tipo
 * @property mixed $valor
 */
class Parametro extends Model
{
    use HasFactory;

    protected $table = 'parametros';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
    ];

    protected $casts = [
        'clave' => 'string',
        'tipo' => 'string',
    ];

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_TEXT = 'text';

    public static function obtener(string $clave, mixed $default = null): mixed
    {
        return Cache::rememberForever(static::cacheKey($clave), function () use ($clave, $default) {
            $parametro = static::query()->where('clave', $clave)->first();

            return $parametro?->valor ?? $default;
        });
    }

    public static function forget(string $clave): void
    {
        Cache::forget(static::cacheKey($clave));
    }

    protected static function cacheKey(string $clave): string
    {
        return sprintf('parametros:%s', $clave);
    }

    protected function valor(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                return match ($this->tipo) {
                    self::TYPE_INTEGER => $value !== null ? (int) $value : null,
                    default => $value,
                };
            },
            set: function ($value) {
                return match ($this->tipo) {
                    self::TYPE_INTEGER => $value !== null ? (string) (int) $value : null,
                    default => $value,
                };
            },
        );
    }
}
