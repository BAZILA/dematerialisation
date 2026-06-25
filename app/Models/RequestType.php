<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'category',
        'type_etudiant',  // 'nouveau' | 'ancien' | 'both'
        'description',
        'is_required',
        'is_active',
        'has_stamp',      // tamponné automatiquement à la validation
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'has_stamp'   => 'boolean',
    ];

    /* ──────────────────────────────────────────────────────
     | Définitions des catégories
     | depot       → nouveau ET/OU ancien (séparés par type_etudiant)
     | autres      → ancien UNIQUEMENT
     ─────────────────────────────────────────────────────── */

    public static array $categories = [
        'depot'       => 'Dépôt de dossier',
        'attestation' => "Demande d'attestation",
        'diplome'     => 'Demande de diplôme',
        'releve'      => 'Demande de relevé de notes',
        'duplicat'    => 'Duplicata de carte étudiant',
    ];

    /** Catégories accessibles à un type d'étudiant donné */
    public static function categoriesFor(string $typeEtudiant): array
    {
        if ($typeEtudiant === 'nouveau') {
            return ['depot' => self::$categories['depot']];
        }
        return self::$categories;
    }

    /* ── Relations ─────────────────────────────────────── */

    public function requestFiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    /* ── Scopes / helpers ──────────────────────────────── */

    /**
     * Retourne les types actifs pour une catégorie et un profil étudiant.
     * Pour le dépôt : filtre par type_etudiant (nouveau, ancien, both).
     * Pour les autres : toujours ancien, donc on retourne tout ce qui est actif.
     */
    public static function forCategory(string $category, string $typeEtudiant = 'both'): \Illuminate\Database\Eloquent\Collection
    {
        // Les demandes (hors dépôt) ne concernent que les anciens
        if ($category !== 'depot' && $typeEtudiant === 'nouveau') {
            return collect();
        }

        return static::where('category', $category)
            ->where('is_active', true)
            ->where(function ($q) use ($typeEtudiant) {
                $q->where('type_etudiant', 'both')
                  ->orWhere('type_etudiant', $typeEtudiant);
            })
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /* ── Accessors ─────────────────────────────────────── */

    public function getCategoryLabelAttribute(): string
    {
        return self::$categories[$this->category] ?? $this->category;
    }

    public function getTypeEtudiantLabelAttribute(): string
    {
        return match($this->type_etudiant) {
            'nouveau' => 'Nouveau seulement',
            'ancien'  => 'Ancien seulement',
            'both'    => 'Nouveau & Ancien',
            default   => $this->type_etudiant,
        };
    }
}
