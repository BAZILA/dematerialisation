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
        'frais_actifs', 'frais_montant',
    ];

    protected $casts = [
        'requires_auth' => 'boolean',
        'pour_nouveaux' => 'boolean',
        'pour_anciens'  => 'boolean',
        'is_active'     => 'boolean',
        'frais_actifs'  => 'boolean',
        'frais_montant' => 'integer',
    ];

    /** Frais exigés pour cette procédure (0 = gratuite). */
    public function fraisDus(): int
    {
        return $this->frais_actifs ? max(0, (int) $this->frais_montant) : 0;
    }

    public function getFraisFormatesAttribute(): string
    {
        return $this->fraisDus() > 0
            ? number_format($this->frais_montant, 0, ',', ' ') . ' FCFA'
            : 'Gratuite';
    }

    public function requestTypes()
    {
        return $this->hasMany(RequestType::class, 'category', 'code');
    }

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
