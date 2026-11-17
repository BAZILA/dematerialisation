<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'numero_identifiant',
        'nom',            // nom réel (personnel / staff)
        'prenom',         // prénom réel (personnel / staff)
        'email',
        'password',
        'role',           // rôle principal dénormalisé (affichage / redirection rapide)
        'type_etudiant',
        'signature_path', // image de signature (staff), pour le verso des fiches
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'         => 'datetime',
        'is_active'                 => 'boolean',
        'doit_changer_mot_de_passe' => 'boolean',   // mot de passe provisoire à remplacer
        'two_factor_secret'         => 'encrypted', // secret TOTP chiffré au repos
        'two_factor_recovery_codes' => 'array',     // codes de secours, hachés un à un
        'two_factor_confirmed_at'   => 'datetime',
    ];

    /** Nombre de codes de secours générés à chaque activation. */
    public const NB_CODES_SECOURS = 8;

    /** 2FA active et confirmée sur ce compte ? */
    public function hasTwoFactor(): bool
    {
        return !empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /* ── Codes de secours 2FA ──────────────────────────────────
     |  Un téléphone perdu bloquerait sinon le compte jusqu'à
     |  l'intervention d'un gestionnaire. Les codes sont hachés :
     |  ils ne sont lisibles qu'au moment où on les génère.
     */

    /**
     * Fabrique une nouvelle série et l'enregistre hachée.
     * Les anciens codes sont invalidés du même coup.
     *
     * @return array<int,string>  codes en clair, à montrer UNE seule fois
     */
    public function genererCodesSecours(): array
    {
        $clairs = [];
        for ($i = 0; $i < self::NB_CODES_SECOURS; $i++) {
            $clairs[] = strtoupper(\Illuminate\Support\Str::random(5) . '-' . \Illuminate\Support\Str::random(5));
        }

        $this->two_factor_recovery_codes = array_map(
            fn ($c) => \Illuminate\Support\Facades\Hash::make($c), $clairs
        );
        $this->save();

        return $clairs;
    }

    /** Combien de codes restent utilisables. */
    public function codesSecoursRestants(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /**
     * Consomme un code de secours s'il correspond. Un code ne sert
     * qu'une fois : il est retiré de la liste dès qu'il est accepté.
     */
    public function consommerCodeSecours(string $saisi): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $saisi = strtoupper(trim($saisi));

        foreach ($codes as $i => $hache) {
            if (\Illuminate\Support\Facades\Hash::check($saisi, $hache)) {
                unset($codes[$i]);
                $this->two_factor_recovery_codes = array_values($codes);
                $this->save();

                return true;
            }
        }

        return false;
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    /* ── Périmètres d'affectation (agent ↔ parcours × semestre) ── */

    public function perimetres()
    {
        return $this->hasMany(AgentPerimetre::class);
    }

    /** L'agent est-il restreint à un périmètre ? (0 ligne = couvre tout) */
    public function hasPerimetres(): bool
    {
        return $this->perimetres()->exists();
    }

    /**
     * Applique le filtre de périmètre à une requête de StudentRequest.
     * - aucun périmètre → aucune restriction (voit tout) ;
     * - sinon → uniquement les dépôts des parcours (× semestres) affectés,
     *   les demandes sans contexte académique restant visibles.
     */
    public function applyPerimeterFilter($query)
    {
        if ($this->isSuperAdmin()) {
            return $query; // le super-admin voit toujours tout
        }

        $perimetres = $this->perimetres()->get();
        if ($perimetres->isEmpty()) {
            return $query; // défaut = tout
        }

        return $query->where(function ($q) use ($perimetres) {
            $q->whereNull('parcours_id'); // demandes hors contexte (non-dépôt)
            foreach ($perimetres as $p) {
                $q->orWhere(function ($qq) use ($p) {
                    $qq->where('parcours_id', $p->parcours_id);
                    if ($p->semestre) {
                        $qq->where('semestre', $p->semestre);
                    }
                });
            }
        });
    }

    /**
     * Périmètre appliqué aux CANDIDATURES : par PARCOURS uniquement
     * (un candidat n'a pas d'année d'études). Une candidature est visible
     * dès qu'un de ses vœux tombe dans le périmètre de l'agent.
     */
    public function applyCandidaturePerimeter($query)
    {
        $ids = $this->perimeterParcoursIds();
        if ($ids === null) {
            return $query; // couvre tout
        }

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('parcours_id', $ids)
              ->orWhereHas('choix', fn ($c) => $c->whereIn('parcours_id', $ids));
        });
    }

    /** L'agent couvre-t-il ce parcours précis ? */
    public function coversParcours(?int $parcoursId): bool
    {
        $ids = $this->perimeterParcoursIds();

        return $ids === null || ($parcoursId !== null && in_array($parcoursId, $ids, true));
    }

    /** L'agent peut-il instruire cette candidature ? */
    public function coversCandidature(Candidature $cand): bool
    {
        $ids = $this->perimeterParcoursIds();
        if ($ids === null) {
            return true;
        }
        $voeux = $cand->relationLoaded('choix')
            ? $cand->choix->pluck('parcours_id')
            : $cand->choix()->pluck('parcours_id');

        return in_array($cand->parcours_id, $ids, true)
            || $voeux->contains(fn ($p) => in_array((int) $p, $ids, true));
    }

    /**
     * Périmètre appliqué aux PAIEMENTS.
     *
     * Un paiement porte soit sur une section de candidature, soit sur une
     * demande étudiante : dans les deux cas on remonte au parcours. Sans
     * ce filtre, un agent restreint verrait la caisse entière — donc les
     * noms et les reçus de candidats hors de son périmètre.
     */
    public function applyPaiementPerimeter($query)
    {
        $ids = $this->perimeterParcoursIds();
        if ($ids === null) {
            return $query;   // couvre tout (caissier central, super-admin)
        }

        return $query->where(function ($q) use ($ids) {
            $q->where(function ($qq) use ($ids) {
                $qq->where('payable_type', \App\Models\CandidatureChoix::class)
                   ->whereIn('payable_id', \App\Models\CandidatureChoix::whereIn('parcours_id', $ids)->select('id'));
            })->orWhere(function ($qq) use ($ids) {
                $qq->where('payable_type', \App\Models\StudentRequest::class)
                   ->whereIn('payable_id', \App\Models\StudentRequest::whereIn('parcours_id', $ids)->select('id'));
            });
        });
    }

    /** L'agent peut-il consulter ce paiement ? */
    public function coversPaiement(\App\Models\Paiement $paiement): bool
    {
        $ids = $this->perimeterParcoursIds();
        if ($ids === null) {
            return true;
        }
        $payable = $paiement->payable;

        $parcoursId = match (true) {
            $payable instanceof \App\Models\CandidatureChoix => $payable->parcours_id,
            $payable instanceof \App\Models\StudentRequest   => $payable->parcours_id,
            default => null,
        };

        // Un paiement sans contexte académique reste visible
        return $parcoursId === null || in_array((int) $parcoursId, $ids, true);
    }

    /**
     * Parcours couverts par le périmètre.
     * null = couvre TOUT (super-admin ou aucune affectation).
     */
    public function perimeterParcoursIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }
        $ids = $this->perimetres()->pluck('parcours_id')->filter()->unique()->values()->all();

        return $ids ?: null;
    }

    /**
     * Codes niveau (A1, A2, …) couverts par le périmètre.
     * null = tous les niveaux (aucune affectation, ou au moins une
     * affectation « toutes années » sur un parcours).
     */
    public function perimeterNiveaux(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }
        $perimetres = $this->perimetres()->get();
        if ($perimetres->isEmpty() || $perimetres->contains(fn ($p) => blank($p->semestre))) {
            return null;
        }

        return $perimetres->pluck('semestre')->unique()->values()->all();
    }

    /** Procédures (catégories) que l'utilisateur a le droit de traiter. */
    public function treatableCategories(): array
    {
        $codes = \App\Models\Procedure::query()->pluck('code');

        if ($this->isSuperAdmin()) {
            return $codes->all();
        }

        return $codes->filter(fn ($code) => $this->can($code . '.traiter'))->values()->all();
    }

    /** Peut-il traiter cette catégorie précise ? */
    public function canTreatCategory(string $code): bool
    {
        return $this->isSuperAdmin() || $this->can($code . '.traiter');
    }

    /** L'agent peut-il traiter cette demande (selon son périmètre) ? */
    public function coversRequest(StudentRequest $sr): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $perimetres = $this->perimetres()->get();
        if ($perimetres->isEmpty() || $sr->parcours_id === null) {
            return true; // pas de restriction, ou demande hors contexte
        }

        return $perimetres->contains(function ($p) use ($sr) {
            return $p->parcours_id === $sr->parcours_id
                && ($p->semestre === null || $p->semestre === $sr->semestre);
        });
    }

    /* ── Helpers de rôle ─────────────────────────────────────────
       L'autorité des permissions est Spatie ; le champ `role` reste
       un rôle principal pratique pour l'affichage et la redirection. */

    /** Personnel du back-office (toute personne y ayant accès). */
    public function isStaff(): bool
    {
        return $this->can('acceder-backoffice');
    }

    /** Compat : « admin » = membre du personnel back-office. */
    public function isAdmin(): bool
    {
        return $this->isStaff();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isStudent(): bool
    {
        return $this->role === 'etudiant' || $this->hasRole('etudiant');
    }

    public function isCandidat(): bool
    {
        return $this->role === 'candidat' || $this->hasRole('candidat');
    }

    /** Nom d'affichage : « Prénom Nom » si renseignés, sinon l'identifiant. */
    public function getStaffNameAttribute(): string
    {
        $full = trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));

        return $full !== '' ? $full : $this->numero_identifiant;
    }
}
