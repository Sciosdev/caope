<?php

namespace App\Models\Concerns;

use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CachesCatalogo
{
    /**
     * Obtiene los registros activos del catálogo desde cache.
     */
    public static function activos(): Collection
    {
        return static::cacheRepository()->remember(
            static::cacheKey('activos'),
            static::cacheTtl(),
            fn () => static::query()->where('activo', true)->orderBy('nombre')->get()
        );
    }

    /**
     * Limpia la cache asociada al catálogo.
     */
    public static function flushCache(): void
    {
        static::cacheRepository()->forget(static::cacheKey('activos'));
    }

    /**
     * @return Repository|TaggedCache
     */
    protected static function cacheRepository()
    {
        $store = config('cache.catalogos_store');
        $repository = Cache::store($store ?: config('cache.default'));
        $tags = array_filter((array) config('cache.catalogos_tags', []));

        if (! empty($tags) && method_exists($repository->getStore(), 'tags')) {
            return $repository->tags($tags);
        }

        return $repository;
    }

    protected static function cacheKey(string $suffix): string
    {
        return sprintf('%s:%s', static::cacheKeyPrefix(), $suffix);
    }

    protected static function cacheKeyPrefix(): string
    {
        return sprintf('catalogos:%s', Str::kebab(class_basename(static::class)));
    }

    protected static function cacheTtl(): int
    {
        return (int) config('cache.catalogos_ttl', 3600);
    }
}
