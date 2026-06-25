<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'type_etudiant',
        'is_required',
        'has_stamp',
        'order',
        'description',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'has_stamp'   => 'boolean',
    ];

    public function submissionFiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /**
     * Retourne les types de documents applicables à un type d'étudiant donné.
     */
    public static function forStudentType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereIn('type_etudiant', [$type, 'both'])
            ->orderBy('order')
            ->get();
    }
}
