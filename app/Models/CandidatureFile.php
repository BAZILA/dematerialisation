<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatureFile extends Model
{
    protected $fillable = [
        'candidature_id', 'request_type_id',
        'original_filename', 'stored_filename', 'file_path', 'mime_type', 'file_size',
    ];

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
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
