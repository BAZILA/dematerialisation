<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domaine extends Model
{
    protected $fillable = ['nom', 'sigle', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function parcours()
    {
        return $this->hasMany(Parcours::class);
    }
}
