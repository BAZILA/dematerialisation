<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatureFile extends Model
{
    protected $fillable = [
        'candidature_id', 'request_type_id',
        'original_filename', 'stored_filename', 'file_path', 'mime_type', 'file_size',
        'statut', 'motif_rejet', 'verifie_par', 'verifie_at',
    ];

    protected $casts = ['verifie_at' => 'datetime'];

    /** fourni = non encore examinée ; valide = acceptée ; rejete = à refaire. */
    public static array $statuts = [
        'fourni' => 'Non examinée',
        'valide' => 'Validée',
        'rejete' => 'À corriger',
    ];

    public function estValidee(): bool
    {
        return $this->statut === 'valide';
    }

    public function estRejetee(): bool
    {
        return $this->statut === 'rejete';
    }

    public function getStatutBadgeAttribute(): string
    {
        return match ($this->statut) {
            'valide' => 'success',
            'rejete' => 'danger',
            default  => 'secondary',
        };
    }

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
    }

    public function verificateur()
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    public function requestType()
    {
        return $this->belongsTo(RequestType::class);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1 << (10 * $pow)), 2) . ' ' . $units[$pow];
    }
}
