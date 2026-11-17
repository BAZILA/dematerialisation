<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bachelier_id',
        'ancien_etudiant_id',
        'nom',
        'prenom',
        'sexe',
        'specialite',
        'date_naissance',
        'lieu_naissance',
        'adresse',
        'telephone',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentRequest::class);
    }

    public function bachelier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bachelier::class);
    }

    public function ancienEtudiant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AncienEtudiant::class, 'ancien_etudiant_id');
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /** N° de suivi (issu du bachelier ou de l'ancien étudiant). */
    public function getNumeroSuiviAttribute(): ?string
    {
        return $this->bachelier?->numero_suivi ?? $this->ancienEtudiant?->numero_suivi;
    }

    /** N° de carte (anciens) ou N° de table (bacheliers), pour l'export. */
    public function getNumeroReferenceAttribute(): ?string
    {
        return $this->ancienEtudiant?->numero_carte ?? $this->bachelier?->numero_table;
    }

    /** Type d'étudiant (délégué au User). */
    public function getTypeEtudiantAttribute(): string
    {
        return $this->user->type_etudiant ?? 'nouveau';
    }
}
