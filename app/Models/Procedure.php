<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Procedure extends Model
{
    protected $fillable = [
        'code', 'nom', 'description',
        'requires_auth', 'pour_nouveaux', 'pour_anciens',
        'is_active', 'ordre',
    ];

    protected $casts = [
        'requires_auth' => 'boolean',
        'pour_nouveaux' => 'boolean',
        'pour_anciens'  => 'boolean',
        'is_active'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('procedures.labels'));
        static::deleted(fn () => Cache::forget('procedures.labels'));
    }

    /** [code => nom] des procédures actives, en cache. */
    public static function labels(): array
    {
        return Cache::rememberForever(
            'procedures.labels',
            fn () => static::where('is_active', true)->orderBy('ordre')->orderBy('id')->pluck('nom', 'code')->toArray()
        );
    }
}
