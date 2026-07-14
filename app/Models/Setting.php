<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    /** Tous les réglages indexés par clé, mis en cache. */
    public static function allCached()
    {
        return Cache::rememberForever('settings.all', fn () => static::all()->keyBy('key'));
    }

    /** Valeur typée d'un réglage (avec défaut). */
    public static function get(string $key, $default = null)
    {
        $s = static::allCached()->get($key);
        return $s ? static::castValue($s->value, $s->type) : $default;
    }

    /** Définit (ou crée) un réglage. */
    public static function set(string $key, $value): void
    {
        $s = static::firstOrNew(['key' => $key]);
        $s->value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $s->save();
    }

    public function typedValue()
    {
        return static::castValue($this->value, $this->type);
    }

    protected static function castValue($value, $type)
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default   => $value,
        };
    }
}
