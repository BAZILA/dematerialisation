<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtudeAnterieure extends Model
{
    protected $table = 'etudes_anterieures';

    protected $fillable = [
        'student_request_id',
        'annee_debut',
        'annee_fin',
        'universite',
        'domaine',
        'etablissement',
        'parcours',
        'credits',
    ];

    public function studentRequest()
    {
        return $this->belongsTo(StudentRequest::class);
    }

    public function getPeriodeAttribute(): string
    {
        return trim(($this->annee_debut ?? '') . ' – ' . ($this->annee_fin ?? ''), ' –');
    }
}
