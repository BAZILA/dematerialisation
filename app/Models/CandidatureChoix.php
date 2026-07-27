<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un choix de parcours d'une candidature (ordre 1 = premier choix). */
class CandidatureChoix extends Model
{
    protected $table = 'candidature_choix';

    protected $fillable = ['candidature_id', 'parcours_id', 'ordre'];

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
    }

    public function parcours()
    {
        return $this->belongsTo(Parcours::class);
    }

    public function getOrdreLabelAttribute(): string
    {
        return $this->ordre === 1 ? '1er choix' : $this->ordre . 'e choix';
    }
}
