<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Candidature extends Model
{
    protected $fillable = [
        'reference', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance',
        'email', 'telephone', 'parcours_id', 'parcours_admis_id', 'annee_academique_id', 'status',
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

    /** Choix de parcours classés par ordre de préférence (1 = premier choix). */
    public function choix()
    {
        return $this->hasMany(CandidatureChoix::class)->orderBy('ordre');
    }

    /** Parcours retenu à l'admission. */
    public function parcoursAdmis()
    {
        return $this->belongsTo(Parcours::class, 'parcours_admis_id');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function files()
    {
        return $this->hasMany(CandidatureFile::class);
    }

    /** Pièces COMMUNES : fournies une fois, valables pour tous les parcours. */
    public function filesCommunes()
    {
        return $this->hasMany(CandidatureFile::class)->whereNull('candidature_choix_id');
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

    /**
     * Recalcule le statut du dossier à partir de ses sections.
     *
     * Le dossier n'a plus de décision propre : il résume celles des
     * parcours. Admis dès qu'un parcours l'accepte ; refusé seulement
     * si tous refusent.
     */
    public function recalculerStatut(): void
    {
        $sections = $this->choix()->get();
        if ($sections->isEmpty()) {
            return;
        }

        $statuts = $sections->pluck('statut');

        $global = match (true) {
            $statuts->contains('admis')          => 'admis',
            $statuts->every(fn ($s) => $s === 'refuse') => 'refuse',
            $statuts->contains('en_correction')  => 'en_correction',
            $statuts->contains('en_instruction') => 'en_instruction',
            default                              => 'soumise',
        };

        // parcours_admis_id : conservé pour les écrans et exports existants
        $premierAdmis = $sections->firstWhere('statut', 'admis');

        $this->update([
            'status'            => $global,
            'parcours_admis_id' => $premierAdmis?->parcours_id,
            'decision_at'       => in_array($global, ['admis', 'refuse'], true)
                ? ($this->decision_at ?? now()) : null,
        ]);
    }

    /** Parcours sur lesquels le candidat est admis. */
    public function parcoursAdmisTous()
    {
        return $this->choix->where('statut', 'admis');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'CAND-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }
}
