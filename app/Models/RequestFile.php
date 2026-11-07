<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestFile extends Model
{
    use \App\Models\Concerns\ReparerNomDeFichier;

    use HasFactory;

    protected $fillable = [
        'student_request_id',
        'request_type_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        // Revue admin
        'is_valid',
        'is_locked',
        'admin_comment',
        'is_stamped',
        'stamped_file_path',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'is_locked' => 'boolean',
        'is_stamped' => 'boolean',   // ← manquait aussi
    ];

    /* ── Relations ─────────────────────────────────────── */

    public function studentRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StudentRequest::class);
    }

    public function requestType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /* ── Helpers ────────────────────────────────────────── */

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
