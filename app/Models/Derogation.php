<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Engagement d'un usager à fournir plus tard une pièce obligatoire.
 * La pièce reste due : la dérogation la diffère, elle ne l'annule pas.
 */
class Derogation extends Model
{
    protected $fillable = [
        'student_request_id', 'request_type_id', 'statut',
        'engagee_at', 'levee_at', 'levee_par', 'motif',
    ];

    protected $casts = [
        'engagee_at' => 'datetime',
        'levee_at'   => 'datetime',
    ];

    public static array $statuts = [
        'engagee' => 'Pièce attendue',
        'levee'   => 'Pièce fournie',
        'refusee' => 'Refusée',
    ];

    public function studentRequest()
    {
        return $this->belongsTo(StudentRequest::class);
    }

    public function requestType()
    {
        return $this->belongsTo(RequestType::class);
    }

    public function leveur()
    {
        return $this->belongsTo(User::class, 'levee_par');
    }

    /** La pièce est-elle encore due ? */
    public function estEnCours(): bool
    {
        return $this->statut === 'engagee';
    }

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->statut] ?? $this->statut;
    }

    public function getStatutBadgeAttribute(): string
    {
        return match ($this->statut) {
            'levee'   => 'success',
            'refusee' => 'danger',
            default   => 'warning',
        };
    }
}
