<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Global key/value configuration for the platform.
 *
 * Usage:
 *   Setting::get('platform_commission_rate', 15);   // typed read (float)
 *   Setting::set('default_delivery_fee', 3.00);
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    /** Cache key holding the whole settings map. */
    protected const CACHE_KEY = 'app.settings';

    /**
     * Read a setting and cast it to its declared type.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $map = static::map();

        if (! array_key_exists($key, $map)) {
            return $default;
        }

        return $map[$key];
    }

    /**
     * All settings as a typed key => value map (cached).
     *
     * @return array<string, mixed>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => $s->castValue(),
            ])->all();
        });
    }

    /**
     * Create or update a setting, then clear the cache.
     */
    public static function set(string $key, mixed $value, ?string $type = null): void
    {
        $row = static::firstOrNew(['key' => $key]);
        $row->type = $type ?? $row->type ?? 'string';
        $row->value = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value);
        $row->save();

        Cache::forget(self::CACHE_KEY);
    }

    /** Convert the stored string into its real PHP type. */
    protected function castValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float'   => (float) $this->value,
            'boolean' => (bool) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    /** Always bust the cache when a row changes through the model. */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
