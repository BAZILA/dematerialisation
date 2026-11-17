<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    protected $fillable = ['faculte_id', 'nom', 'sigle', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function faculte()
    {
        return $this->belongsTo(Faculte::class);
    }

    public function parcours()
    {
        return $this->hasMany(Parcours::class);
    }
}
