<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'annee_debut',
        'annee_fin',
        'universite',
        'parcours',
        'credits',
    ];

    public function submission(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function getPeriodeAttribute(): string
    {
        return $this->annee_debut . ' – ' . $this->annee_fin;
    }
}
