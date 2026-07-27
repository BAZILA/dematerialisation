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

    /**
     * Parcours auxquels cette pièce est PROPRE.
     * Relation vide = pièce COMMUNE, réclamée à tout le monde.
     */
    public function parcours(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Parcours::class, 'parcours_request_type');
    }

    /* ── Portée : commune ou propre à des parcours ──────── */

    /** Pièce demandée à tous les candidats, quel que soit le parcours ? */
    public function estCommune(): bool
    {
        return $this->relationLoaded('parcours')
            ? $this->parcours->isEmpty()
            : !$this->parcours()->exists();
    }

    public function getPorteeLabelAttribute(): string
    {
        if ($this->estCommune()) {
            return 'Commune à tous les parcours';
        }
        $noms = $this->parcours->pluck('nom');

        return 'Propre à : ' . $noms->implode(', ');
    }

    /**
     * Pièces à fournir pour une candidature, selon les parcours choisis :
     * les pièces communes + celles propres aux parcours retenus.
     *
     * @param  array<int>  $parcoursIds  vide = uniquement les pièces communes
     */
    public static function pourCandidature(array $parcoursIds = []): \Illuminate\Database\Eloquent\Collection
    {
        $ids = array_values(array_filter(array_map('intval', $parcoursIds)));

        return static::with('parcours')
            ->where('category', 'candidature')
            ->where('is_active', true)
            ->where(function ($q) use ($ids) {
                $q->whereDoesntHave('parcours');
                if ($ids) {
                    $q->orWhereHas('parcours', fn ($p) => $p->whereIn('parcours.id', $ids));
                }
            })
            ->orderBy('order')->orderBy('id')
            ->get();
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
