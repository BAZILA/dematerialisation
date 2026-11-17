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
        'grade',          // niveau d'études : licence, master… (obligatoire)
        'type_etudiant',  // 'nouveau' | 'ancien' | 'both'
        'description',
        'is_required', 'derogation_possible',
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
        'derogation_possible' => 'boolean',
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

    /** Libellé lisible du niveau porteur. */
    public function getGradeLabelAttribute(): string
    {
        return Parcours::$grades[$this->grade]['label'] ?? $this->grade ?? '—';
    }

    /* ── Portée : commune, par grade, ou par parcours ───── */

    /**
     * Pièce réclamée à TOUS les parcours de son niveau ?
     *
     * « Commune » se lit désormais à l'intérieur d'un niveau : une pièce
     * commune à la licence n'est jamais demandée à un master. Les
     * niveaux partagent les procédures, jamais les pièces.
     */
    public function estCommune(): bool
    {
        return $this->relationLoaded('parcours')
            ? $this->parcours->isEmpty()
            : !$this->parcours()->exists();
    }

    public function getPorteeLabelAttribute(): string
    {
        $niveau = $this->grade_label;

        if ($this->estCommune()) {
            return "Tout le niveau {$niveau}";
        }

        $noms = ($this->relationLoaded('parcours') ? $this->parcours : $this->parcours()->get())
            ->pluck('nom');

        return $noms->isEmpty() ? "Tout le niveau {$niveau}" : $noms->implode(', ');
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

        // Le niveau se déduit des vœux : candidater en master n'appelle
        // jamais les pièces de la licence, et réciproquement.
        $grades = $ids
            ? Parcours::whereIn('id', $ids)->pluck('grade')->filter()->unique()->values()->all()
            : [];

        // Aucun vœu exprimé : rien à réclamer tant qu'on ignore le niveau.
        if (!$grades) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return static::with('parcours')
            ->where('category', 'candidature')
            ->where('is_active', true)
            ->whereIn('grade', $grades)
            ->where(function ($q) use ($ids) {
                // Commune au niveau : aucun parcours désigné.
                $q->whereDoesntHave('parcours')
                  ->orWhereHas('parcours', fn ($p) => $p->whereIn('parcours.id', $ids));
            })
            ->orderBy('order')->orderBy('id')
            ->get();
    }

    /* ── Scopes / helpers ──────────────────────────────── */

    /**
     * Types actifs pour une procédure et un profil étudiant.
     * Une procédure non ouverte aux nouveaux → rien pour un nouveau.
     */
    public static function forCategory(
        string $category,
        string $typeEtudiant = 'both',
        ?string $grade = null,
        ?int $parcoursId = null
    ): \Illuminate\Database\Eloquent\Collection {
        $proc = Procedure::where('code', $category)->first();
        if ($typeEtudiant === 'nouveau' && $proc && !$proc->pour_nouveaux) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Sans niveau connu, on ne peut rien réclamer de juste : mieux
        // vaut une liste vide qu'un dossier exigeant les pièces d'un
        // autre cursus.
        if (!$grade) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return static::with('parcours')
            ->where('category', $category)
            ->where('is_active', true)
            ->where('grade', $grade)
            ->where(function ($q) use ($typeEtudiant) {
                $q->where('type_etudiant', 'both')
                  ->orWhere('type_etudiant', $typeEtudiant);
            })
            ->where(function ($q) use ($parcoursId) {
                // Commune au niveau, ou visant précisément ce parcours.
                $q->whereDoesntHave('parcours');
                if ($parcoursId) {
                    $q->orWhereHas('parcours', fn ($p) => $p->where('parcours.id', $parcoursId));
                }
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
