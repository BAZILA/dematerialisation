<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'parcours_id',          // contexte académique du dépôt
        'annee_academique_id',
        'semestre',
        'engagement_accepte',   // « Lu et approuvé » confirmé avant soumission
        'category',
        'status',
        'version',
        'submitted_at',
        'validated_at',
        'validated_by',
        'rejection_message',
        'admin_comment',
        'rendez_vous_at',    // date de retrait physique (demandes non-dépôt)
        'rendez_vous_note',  // instructions de retrait
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rendez_vous_at' => 'datetime',
        'engagement_accepte' => 'boolean',
    ];

    /** Saison d'un semestre : 1/3/5 = Harmattan, 2/4/6 = Mousson. */
    public static function saison(?string $semestre): ?string
    {
        if (!$semestre || !preg_match('/(\d+)/', $semestre, $m)) {
            return null;
        }
        return ((int) $m[1]) % 2 === 1 ? 'Harmattan' : 'Mousson';
    }

    /* ── Relations ──────────────────────────────────────── */

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    public function validator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function parcours(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Parcours::class);
    }

    public function anneeAcademique(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function etudesAnterieures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EtudeAnterieure::class);
    }

    /* ── Helpers statut ─────────────────────────────────── */

    public function isDraft(): bool
    {
        return $this->status === 'brouillon';
    }
    public function isSubmitted(): bool
    {
        return $this->status === 'soumis';
    }
    public function isInCorrection(): bool
    {
        return $this->status === 'en_correction';
    }
    public function isValidated(): bool
    {
        return $this->status === 'validé';
    }
    public function isRejected(): bool
    {
        return $this->status === 'rejeté';
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['brouillon', 'en_correction']);
    }

    /* ── Helpers fichiers ───────────────────────────────── */

    public function fileForType(RequestType $type): ?RequestFile
    {
        return $this->files->firstWhere('request_type_id', $type->id);
    }

    public function allRequiredFilesUploaded(): bool
    {
        $required = RequestType::forCategory(
            $this->category,
            $this->student->user->type_etudiant
        )->where('is_required', true);

        foreach ($required as $type) {
            if (!$this->fileForType($type))
                return false;
        }
        return true;
    }

    /* ── Accessors ──────────────────────────────────────── */

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'secondary',
            'soumis' => 'warning',
            'en_correction' => 'info',
            'validé' => 'success',
            'rejeté' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'Brouillon',
            'soumis' => 'En attente',
            'en_correction' => 'À corriger',
            'validé' => 'Validé',
            'rejeté' => 'Rejeté',
            default => $this->status,
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return RequestType::$categories[$this->category] ?? $this->category;
    }
}
