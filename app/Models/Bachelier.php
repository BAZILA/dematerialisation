<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bachelier extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_suivi',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'serie_bac',
        'annee_bac',
        'numero_releve',
        'numero_table',
        'academie',
        'telephone',
        'email',
        'is_registered',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'is_registered'  => 'boolean',
    ];

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }
}
