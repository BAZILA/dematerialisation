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

    /**
     * Grades auxquels cette pièce est demandée (licence, master…).
     * Se cumule avec la portée par parcours.
     */
    public function grades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RequestTypeGrade::class);
    }

    /** Codes des grades couverts, sous forme de tableau simple. */
    public function gradesCodes(): array
    {
        return ($this->relationLoaded('grades') ? $this->grades : $this->grades()->get())
            ->pluck('grade')->all();
    }

    /* ── Portée : commune, par grade, ou par parcours ───── */

    /**
     * Pièce demandée à tous les candidats ?
     * Vrai seulement si AUCUNE restriction n'est posée — ni grade,
     * ni parcours. Une pièce réservée aux masters n'est pas commune.
     */
    public function estCommune(): bool
    {
        $sansParcours = $this->relationLoaded('parcours')
            ? $this->parcours->isEmpty()
            : !$this->parcours()->exists();

        $sansGrade = $this->relationLoaded('grades')
            ? $this->grades->isEmpty()
            : !$this->grades()->exists();

        return $sansParcours && $sansGrade;
    }

    public function getPorteeLabelAttribute(): string
    {
        if ($this->estCommune()) {
            return 'Commune à tous les parcours';
        }

        $morceaux = [];

        $grades = collect($this->gradesCodes())
            ->map(fn ($g) => Parcours::$grades[$g]['label'] ?? $g);
        if ($grades->isNotEmpty()) {
            $morceaux[] = 'Tout ' . $grades->implode(', ');
        }

        $noms = $this->parcours->pluck('nom');
        if ($noms->isNotEmpty()) {
            $morceaux[] = $noms->implode(', ');
        }

        return 'Propre à : ' . implode(' + ', $morceaux);
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

        // Les grades se déduisent des parcours visés : demander « tous les
        // masters » ne doit pas obliger à les énumérer un par un.
        $grades = $ids
            ? Parcours::whereIn('id', $ids)->pluck('grade')->filter()->unique()->values()->all()
            : [];

        return static::with('parcours', 'grades')
            ->where('category', 'candidature')
            ->where('is_active', true)
            ->where(function ($q) use ($ids, $grades) {
                // Commune : aucune restriction, ni parcours ni grade
                $q->where(fn ($sq) => $sq->whereDoesntHave('parcours')->whereDoesntHave('grades'));

                if ($grades) {
                    $q->orWhereHas('grades', fn ($g) => $g->whereIn('grade', $grades));
                }
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
