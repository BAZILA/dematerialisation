<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    protected $fillable = [
        'annee_academique_id', 'operation', 'grade', 'parcours_id',
        'date_debut', 'date_fin', 'is_open',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'is_open'    => 'boolean',
    ];

    /** Anciens libellés (fallback si la procédure a disparu de la table). */
    public static array $operations = [
        'candidature'  => 'Candidatures',
        'depot'        => 'Dépôts définitifs',
        'attestation'  => 'Attestations',
        'releve'       => 'Relevés de notes',
        'diplome'      => 'Diplômes',
        'duplicat'     => 'Duplicatas',
        'homologation' => 'Homologations',
    ];

    /**
     * Liste DYNAMIQUE des opérations paramétrables : toutes les procédures
     * actives (y compris celles créées depuis l'interface). [code => nom]
     */
    public static function operations(): array
    {
        $procs = Procedure::labels();

        return $procs ?: self::$operations;
    }

    public function annee()
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_academique_id');
    }

    public function parcours()
    {
        return $this->belongsTo(Parcours::class);
    }

    /* --- Ciblage : générale, par niveau, ou par parcours --- */

    /** Règle sans cible : elle vaut pour toute l'université. */
    public function estGenerale(): bool
    {
        return $this->grade === null && $this->parcours_id === null;
    }

    /**
     * Précision de la règle : plus le chiffre est haut, plus elle
     * l'emporte. Un parcours nommé bat son niveau, qui bat la
     * règle générale.
     */
    public function precision(): int
    {
        if ($this->parcours_id !== null) {
            return 2;
        }

        return $this->grade !== null ? 1 : 0;
    }

    public function getCibleLabelAttribute(): string
    {
        if ($this->parcours_id !== null) {
            return $this->parcours?->nom ?? 'Parcours supprimé';
        }
        if ($this->grade !== null) {
            return 'Tout ' . (Parcours::$grades[$this->grade]['label'] ?? $this->grade);
        }

        return "Toute l'université";
    }

    /** La fenêtre est-elle ouverte aujourd'hui ? (drapeau + dates) */
    public function ouverteAujourdhui(): bool
    {
        if (!$this->is_open) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->date_debut && $today->lt($this->date_debut)) {
            return false;
        }
        if ($this->date_fin && $today->gt($this->date_fin->copy()->endOfDay())) {
            return false;
        }

        return true;
    }

    public function getOperationLabelAttribute(): string
    {
        return self::operations()[$this->operation]
            ?? self::$operations[$this->operation]
            ?? $this->operation;
    }
}
