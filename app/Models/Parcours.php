<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcours extends Model
{
    protected $table = 'parcours';

    protected $fillable = ['departement_id', 'nom', 'sigle', 'grade', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Grades → semestres (paramétrable ultérieurement). */
    public static array $grades = [
        'licence'        => ['label' => 'Licence',        'semestres' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6']],
        'master'         => ['label' => 'Master',         'semestres' => ['S1', 'S2', 'S3', 'S4']],
        'doctorat'       => ['label' => 'Doctorat',       'semestres' => ['Année 1', 'Année 2', 'Année 3']],
        'specialisation' => ['label' => 'Spécialisation', 'semestres' => ['S1', 'S2']],
        'certificat'     => ['label' => 'Certificat',     'semestres' => ['S1', 'S2']],
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function getGradeLabelAttribute(): string
    {
        return self::$grades[$this->grade]['label'] ?? $this->grade;
    }

    /** Semestres du grade de ce parcours. */
    public function semestres(): array
    {
        return self::$grades[$this->grade]['semestres'] ?? [];
    }
}
