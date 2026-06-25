<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'status',
        'version',
        'submitted_at',
        'validated_at',
        'validated_by',
        'rejection_message',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function academicHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AcademicHistory::class);
    }

    public function validator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /* ── Status helpers ─────────────────────────────────────────────────── */

    public function isDraft(): bool     { return $this->status === 'brouillon'; }
    public function isSubmitted(): bool { return $this->status === 'soumis'; }
    public function isValidated(): bool { return $this->status === 'validé'; }
    public function isRejected(): bool  { return $this->status === 'rejeté'; }

    public function canEdit(): bool
    {
        return in_array($this->status, ['brouillon', 'rejeté']);
    }

    /* ── File helpers ───────────────────────────────────────────────────── */

    /**
     * Retourne le fichier uploadé pour un type de document donné, ou null.
     */
    public function fileForType(DocumentType $docType): ?SubmissionFile
    {
        return $this->files->firstWhere('document_type_id', $docType->id);
    }

    /**
     * Vérifie si tous les documents obligatoires sont uploadés.
     */
    public function allRequiredFilesUploaded(): bool
    {
        $requiredTypes = DocumentType::forStudentType($this->student->type_etudiant)
            ->where('is_required', true);

        foreach ($requiredTypes as $type) {
            if (!$this->fileForType($type)) {
                return false;
            }
        }
        return true;
    }

    /* ── Accessors ──────────────────────────────────────────────────────── */

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'brouillon' => 'secondary',
            'soumis'    => 'warning',
            'validé'    => 'success',
            'rejeté'    => 'danger',
            default     => 'secondary',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'brouillon' => 'fa-pen',
            'soumis'    => 'fa-hourglass-half',
            'validé'    => 'fa-check-circle',
            'rejeté'    => 'fa-times-circle',
            default     => 'fa-question-circle',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'brouillon' => 'Brouillon',
            'soumis'    => 'En Attente',
            'validé'    => 'Validé',
            'rejeté'    => 'À Corriger',
            default     => $this->status,
        };
    }
}
