<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'parcours_id',          // contexte académique du dépôt
        'annee_academique_id',
        'semestre',
        'engagement_accepte',   // « Lu et approuvé » confirmé avant soumission
        'category',
        'status',
        'version',
        'submitted_at',
        'validated_at',
        'validated_by',
        'rejection_message',
        'admin_comment',
        'rendez_vous_at',    // date de retrait physique (demandes non-dépôt)
        'rendez_vous_note',  // instructions de retrait
    ];

    protected $casts = [
        /* Les cles etrangeres ne sont pas converties d'office, contrairement
           a la cle primaire. Selon le pilote et sa configuration, MySQL peut
           les rendre sous forme de TEXTE : « 12 » !== 12 est alors vrai, et
           le proprietaire legitime d'un dossier se voyait refuser l'acces.
           On les fixe en entiers, pour que toute comparaison tienne. */
        'student_id' => 'integer',
        'parcours_id' => 'integer',
        'annee_academique_id' => 'integer',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rendez_vous_at' => 'datetime',
        'engagement_accepte' => 'boolean',
    ];

    /** Saison d'un semestre : 1/3/5 = Harmattan, 2/4/6 = Mousson. */
    public static function saison(?string $semestre): ?string
    {
        if (!$semestre || !preg_match('/(\d+)/', $semestre, $m)) {
            return null;
        }
        return ((int) $m[1]) % 2 === 1 ? 'Harmattan' : 'Mousson';
    }

    /* ── Relations ──────────────────────────────────────── */

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    public function validator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function parcours(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Parcours::class);
    }

    public function anneeAcademique(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function etudesAnterieures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EtudeAnterieure::class);
    }

    /* ── Helpers statut ─────────────────────────────────── */

    public function isDraft(): bool
    {
        return $this->status === 'brouillon';
    }
    public function isSubmitted(): bool
    {
        return $this->status === 'soumis';
    }
    public function isInCorrection(): bool
    {
        return $this->status === 'en_correction';
    }
    public function isValidated(): bool
    {
        return $this->status === 'validé';
    }
    public function isRejected(): bool
    {
        return $this->status === 'rejeté';
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['brouillon', 'en_correction']);
    }

    /* ── Helpers fichiers ───────────────────────────────── */

    public function fileForType(RequestType $type): ?RequestFile
    {
        return $this->files->firstWhere('request_type_id', $type->id);
    }

    /* --- Derogations : pieces obligatoires differees --- */

    public function derogations()
    {
        return $this->hasMany(Derogation::class);
    }

    /** Pieces encore dues au titre d'une derogation. */
    public function derogationsEnCours()
    {
        return $this->derogations()->where('statut', 'engagee');
    }

    /** Le dossier avance-t-il avec une piece encore attendue ? */
    public function sousDerogation(): bool
    {
        return $this->relationLoaded('derogations')
            ? $this->derogations->where('statut', 'engagee')->isNotEmpty()
            : $this->derogationsEnCours()->exists();
    }

    /**
     * Pieces obligatoires absentes que l'usager PEUT differer.
     * Les autres restent bloquantes : toutes ne se prêtent pas au report.
     *
     * @return \Illuminate\Support\Collection<int,RequestType>
     */
    public function piecesDerogeables()
    {
        return $this->piecesObligatoiresManquantes()
            ->filter(fn (RequestType $t) => (bool) $t->derogation_possible)
            ->values();
    }

    /** Pieces obligatoires absentes qui bloquent encore la soumission. */
    public function piecesBloquantes()
    {
        $derogees = $this->derogations()->where('statut', 'engagee')
            ->pluck('request_type_id')->all();

        return $this->piecesObligatoiresManquantes()
            ->reject(fn (RequestType $t) => in_array($t->id, $derogees, true))
            ->values();
    }

    /**
     * Niveau d'études dont relève ce dossier.
     *
     * Il vient du parcours choisi : c'est lui qui décide des pièces à
     * fournir, un master ne devant pas fournir ce qu'une licence fournit.
     */
    public function grade(): ?string
    {
        return $this->parcours?->grade;
    }

    /** Les pièces attendues pour CE dossier, à son niveau. */
    public function typesAttendus(): \Illuminate\Database\Eloquent\Collection
    {
        return RequestType::forCategory(
            $this->category,
            $this->student->user->type_etudiant,
            $this->grade(),
            $this->parcours_id
        );
    }

    /**
     * Les pieces a AFFICHER : celles attendues a ce niveau, plus toute
     * piece reellement deposee — meme si sa fiche a change depuis.
     *
     * « Attendues » et « deposees » ne se recouvrent pas toujours. Une
     * piece desactivee, supprimee du niveau ou deplacee apres le depot
     * sortirait de la liste attendue ; si l'ecran ne parcourait que
     * celle-ci, le fichier deja fourni deviendrait invisible pour l'agent
     * qui doit le traiter. Pour un systeme d'archives, ce qui a ete
     * depose ne doit jamais disparaitre de la vue.
     *
     * La soumission, elle, continue de s'appuyer sur typesAttendus() :
     * on n'exige que ce que le niveau reclame aujourd'hui.
     */
    public function typesAAfficher(): \Illuminate\Database\Eloquent\Collection
    {
        $this->loadMissing('files.requestType');

        $deposes = $this->files->pluck('requestType')->filter();

        return $this->typesAttendus()
            ->concat($deposes)
            ->unique('id')
            ->sortBy([['order', 'asc'], ['id', 'asc']])
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int,RequestType> */
    public function piecesObligatoiresManquantes()
    {
        return $this->typesAttendus()
            ->where('is_required', true)
            ->reject(fn (RequestType $t) => (bool) $this->fileForType($t))
            ->values();
    }

    public function allRequiredFilesUploaded(): bool
    {
        foreach ($this->typesAttendus()->where('is_required', true) as $type) {
            if (!$this->fileForType($type))
                return false;
        }

        return true;
    }

    /* ── Accessors ──────────────────────────────────────── */

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'secondary',
            'soumis' => 'warning',
            'en_correction' => 'info',
            'validé' => 'success',
            'rejeté' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'Brouillon',
            'soumis' => 'En attente',
            'en_correction' => 'À corriger',
            'validé' => 'Validé',
            'rejeté' => 'Rejeté',
            default => $this->status,
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return RequestType::categories()[$this->category] ?? $this->category;
    }
}
