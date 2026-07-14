<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    protected $fillable = ['annee_academique_id', 'operation', 'date_debut', 'date_fin', 'is_open'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'is_open'    => 'boolean',
    ];

    public static array $operations = [
        'candidature'  => 'Candidatures',
        'depot'        => 'Dépôts définitifs',
        'attestation'  => 'Attestations',
        'releve'       => 'Relevés de notes',
        'diplome'      => 'Diplômes',
        'duplicat'     => 'Duplicatas',
        'homologation' => 'Homologations',
    ];

    public function annee()
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_academique_id');
    }

    public function getOperationLabelAttribute(): string
    {
        return self::$operations[$this->operation] ?? $this->operation;
    }
}
