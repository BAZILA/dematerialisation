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

    public function submissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Submission::class);
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

    public function currentSubmission(): ?Submission
    {
        return $this->submissions()->latest()->first();
    }

    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /** Type d'étudiant (délégué au User). */
    public function getTypeEtudiantAttribute(): string
    {
        return $this->user->type_etudiant ?? 'nouveau';
    }

    /** Types de documents requis selon le profil. */
    public function documentTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentType::forStudentType($this->type_etudiant);
    }
}
