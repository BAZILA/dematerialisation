<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnneeAcademique extends Model
{
    protected $table = 'annees_academiques';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'statut', 'is_active'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'is_active'  => 'boolean',
    ];

    public static array $statuts = [
        'en_preparation' => 'En préparation',
        'ouverte'        => 'Ouverte',
        'cloturee'       => 'Clôturée',
        'archivee'       => 'Archivée',
    ];

    public function periodes()
    {
        return $this->hasMany(Periode::class);
    }

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->statut] ?? $this->statut;
    }

    /** Année passée : lecture seule (aucune opération). */
    public function isReadOnly(): bool
    {
        return in_array($this->statut, ['cloturee', 'archivee'], true);
    }

    /** L'année de contexte courante. */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /** Une opération est-elle actuellement ouverte pour cette année ? */
    public function operationOpen(string $operation): bool
    {
        if ($this->statut !== 'ouverte') {
            return false;
        }
        $p = $this->periodes->firstWhere('operation', $operation);
        if (!$p || !$p->is_open) {
            return false;
        }
        $today = now()->startOfDay();
        if ($p->date_debut && $today->lt($p->date_debut)) {
            return false;
        }
        if ($p->date_fin && $today->gt($p->date_fin->copy()->endOfDay())) {
            return false;
        }
        return true;
    }
}
