<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'category',       // code de procédure (table procedures)
        'type_etudiant',  // 'nouveau' | 'ancien' | 'both'
        'description',
        'is_required',
        'is_active',
        'has_stamp',      // tamponné automatiquement à la validation
        'fills_verso',    // fiche dont le verso est rempli automatiquement
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'has_stamp'   => 'boolean',
        'fills_verso' => 'boolean',
    ];

    /* ── Catégories = procédures (dynamiques, gérables depuis l'interface) ── */

    /** [code => nom] de toutes les procédures actives. */
    public static function categories(): array
    {
        return Procedure::labels();
    }

    /** Procédures accessibles à un type d'étudiant donné (espace authentifié). */
    public static function categoriesFor(string $typeEtudiant): array
    {
        $col = $typeEtudiant === 'nouveau' ? 'pour_nouveaux' : 'pour_anciens';

        return Procedure::where('is_active', true)
            ->where('requires_auth', true)
            ->where($col, true)
            ->orderBy('ordre')->orderBy('id')
            ->pluck('nom', 'code')->toArray();
    }

    /* ── Relations ─────────────────────────────────────── */

    public function requestFiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    /* ── Scopes / helpers ──────────────────────────────── */

    /**
     * Types actifs pour une procédure et un profil étudiant.
     * Une procédure non ouverte aux nouveaux → rien pour un nouveau.
     */
    public static function forCategory(string $category, string $typeEtudiant = 'both'): \Illuminate\Database\Eloquent\Collection
    {
        $proc = Procedure::where('code', $category)->first();
        if ($typeEtudiant === 'nouveau' && $proc && !$proc->pour_nouveaux) {
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
        return self::categories()[$this->category] ?? $this->category;
    }

    public function getTypeEtudiantLabelAttribute(): string
    {
        return match ($this->type_etudiant) {
            'nouveau' => 'Nouveau seulement',
            'ancien'  => 'Ancien seulement',
            'both'    => 'Nouveau & Ancien',
            default   => $this->type_etudiant,
        };
    }
}
