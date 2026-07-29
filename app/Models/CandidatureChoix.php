<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une SECTION de candidature : la postulation à UN parcours.
 *
 * Porte les pièces propres à ce parcours et sa propre quittance. Les
 * pièces communes restent rattachées au dossier et sont partagées par
 * toutes les sections — le candidat ne les fournit qu'une fois.
 */
class CandidatureChoix extends Model
{
    protected $table = 'candidature_choix';

    protected $fillable = [
        'candidature_id', 'parcours_id', 'ordre', 'soumis_at',
        'statut', 'motif', 'decide_par', 'decide_at',
    ];

    protected $casts = ['soumis_at' => 'datetime', 'decide_at' => 'datetime'];

    /** Chaque parcours a sa propre décision : on peut être admis partout. */
    public static array $statuts = [
        'soumise'        => 'Soumise',
        'en_instruction' => 'En instruction',
        'en_correction'  => 'À compléter',
        'admis'          => 'Admis',
        'refuse'         => 'Refusé',
    ];

    public function decideur()
    {
        return $this->belongsTo(User::class, 'decide_par');
    }

    public function estAdmis(): bool
    {
        return $this->statut === 'admis';
    }

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->statut] ?? $this->statut;
    }

    public function getStatutBadgeAttribute(): string
    {
        return match ($this->statut) {
            'admis'          => 'success',
            'refuse'         => 'danger',
            'en_correction'  => 'secondary',
            'en_instruction' => 'info',
            default          => 'warning',
        };
    }

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
    }

    public function parcours()
    {
        return $this->belongsTo(Parcours::class);
    }

    /** Pièces PROPRES à ce parcours (les communes sont sur le dossier). */
    public function files()
    {
        return $this->hasMany(CandidatureFile::class, 'candidature_choix_id');
    }

    /** Quittance propre à cette section. */
    public function paiement()
    {
        return $this->morphOne(Paiement::class, 'payable')->latestOfMany();
    }

    public function getOrdreLabelAttribute(): string
    {
        return $this->ordre === 1 ? '1er choix' : $this->ordre . 'e choix';
    }

    /** Le candidat a-t-il été admis sur ce parcours ? */
    public function estRetenu(): bool
    {
        return $this->estAdmis();
    }
}
