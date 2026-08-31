<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rattachement d'une pièce attendue à un GRADE (licence, master…).
 * Le grade n'est pas une entité à part entière : c'est une valeur de
 * Parcours::$grades. Cette table sert donc uniquement de portée.
 */
class RequestTypeGrade extends Model
{
    protected $table = 'request_type_grade';

    protected $fillable = ['request_type_id', 'grade'];

    public function requestType()
    {
        return $this->belongsTo(RequestType::class);
    }

    public function getGradeLabelAttribute(): string
    {
        return Parcours::$grades[$this->grade]['label'] ?? $this->grade;
    }
}
