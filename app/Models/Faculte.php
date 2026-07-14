<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faculte extends Model
{
    protected $table = 'facultes';

    protected $fillable = ['nom', 'sigle', 'type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static array $types = [
        'faculte'  => 'Faculté',
        'institut' => 'Institut',
    ];

    public function departements()
    {
        return $this->hasMany(Departement::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }
}
