<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AncienEtudiant extends Model
{
    use HasFactory;

    protected $table = 'anciens_etudiants';

    protected $fillable = [
        'numero_carte',
        'numero_suivi',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'specialite',
        'derniere_universite',
        'telephone',
        'email',
        'adresse',
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
