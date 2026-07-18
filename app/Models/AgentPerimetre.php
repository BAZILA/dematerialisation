<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Affectation d'un agent à un périmètre (parcours × semestre).
 * Un agent SANS aucune ligne couvre TOUT par défaut ; dès qu'il a au
 * moins une affectation, il est restreint à celles-ci (surtout côté dépôt).
 */
class AgentPerimetre extends Model
{
    protected $table = 'agent_perimetres';

    protected $fillable = ['user_id', 'parcours_id', 'semestre'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parcours()
    {
        return $this->belongsTo(Parcours::class);
    }

    public function getSemestreLabelAttribute(): string
    {
        return $this->semestre ?: 'Tous les semestres';
    }
}
