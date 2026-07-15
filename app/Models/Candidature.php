<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Candidature extends Model
{
    protected $fillable = [
        'reference', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance',
        'email', 'telephone', 'parcours_id', 'annee_academique_id', 'status',
        'admin_comment', 'traite_par', 'decision_at',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'decision_at'    => 'datetime',
    ];

    public static array $statuts = [
        'soumise'        => 'Soumise',
        'en_instruction' => 'En instruction',
        'en_correction'  => 'À compléter',
        'admis'          => 'Admise',
        'refuse'         => 'Refusée',
    ];

    public function parcours()
    {
        return $this->belongsTo(Parcours::class);
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function files()
    {
        return $this->hasMany(CandidatureFile::class);
    }

    public function traiteur()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'soumise'        => 'warning',
            'en_instruction' => 'info',
            'en_correction'  => 'secondary',
            'admis'          => 'success',
            'refuse'         => 'danger',
            default          => 'secondary',
        };
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'CAND-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }
}
