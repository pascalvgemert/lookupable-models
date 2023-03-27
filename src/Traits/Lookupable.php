<?php

namespace App\Services\Lookupable\Traits;

use App\Services\Lookupable\LookupStore;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait Lookupable
{
    protected static array $lookupStore = [];

    public static function lookupAll(): Collection
    {
        return static::fromStore(
            self::getLookupKeyForModel('all'),
            fn () => self::query()->get()
        );
    }

    public static function lookupAllFromCache(): Collection
    {
        return static::fromStore((
            self::getLookupKeyForModel('all'),
            fn () => self::query()->get(),
            true
        );
    }

    public static function lookup(string $column, mixed $value): Model|self|null
    {
        return self::lookupAll()->firstWhere($column, $value);
    }

    public static function lookupFromCache(string $column, mixed $value): Model|self|null
    {
        return self::lookupAllFromCache()->firstWhere($column, $value);
    }

    public static function lookupOrFail(string $column, mixed $value): Model|self
    {
        if (! $model = self::lookup($column, $value)) {
            throw (new ModelNotFoundException())->setModel(self::class);
        }

        return $model;
    }

    public static function lookupFromCacheOrFail(string $column, mixed $value): Model|self
    {
        if (! $model = self::lookupFromCache($column, $value)) {
            throw (new ModelNotFoundException())->setModel(self::class);
        }

        return $model;
    }

    public static function lookupMany(string $column, array|Arrayable $values): Collection
    {
        return self::lookupAll()->filter(
            fn (Model $model) => in_array($model->getAttribute($column), $values)
        );
    }

    public static function lookupManyFromCache(string $column, array|Arrayable $values): Collection
    {
        return self::lookupAllFromCache()->filter(
            fn (Model $model) => in_array($model->getAttribute($column), $values)
        );
    }

    private static function getLookupKeyForModel(string $key): string
    {
        return Str::snake(class_basename(self::class)) . ".{$key}";
    }

    protected static function fromStore(string $key, Closure $callback, $checkCache = false): Model|Collection
    {
        $value = static::$lookupStore[$key] ?? null;

        if (!$value) {
            static::$lookupStore[$key] = $checkCache
                ? Cache::rememberForever($key, fn () => call_user_func($callback))
                : call_user_func($callback);
        }

        return $value ?: static::$lookupStore[$key];
    }
}
