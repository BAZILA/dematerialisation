<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnneeAcademique extends Model
{
    protected $table = 'annees_academiques';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'statut', 'is_active'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'is_active'  => 'boolean',
    ];

    public static array $statuts = [
        'en_preparation' => 'En préparation',
        'ouverte'        => 'Ouverte',
        'cloturee'       => 'Clôturée',
        'archivee'       => 'Archivée',
    ];

    public function periodes()
    {
        return $this->hasMany(Periode::class);
    }

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->statut] ?? $this->statut;
    }

    /** Année passée : lecture seule (aucune opération). */
    public function isReadOnly(): bool
    {
        return in_array($this->statut, ['cloturee', 'archivee'], true);
    }

    /* Mémoïsation par requête HTTP : active()/context() sont appelés à
       plusieurs endroits (garde-fous, vues, sidebar) — une seule requête SQL. */
    protected static ?self $activeMemo = null;
    protected static bool $activeLoaded = false;
    protected static array $findMemo = [];

    /** À appeler après avoir changé l'année active (invalide la mémo). */
    public static function flushMemo(): void
    {
        static::$activeMemo = null;
        static::$activeLoaded = false;
        static::$findMemo = [];
    }

    /** L'année officiellement active (paramétrage). */
    public static function active(): ?self
    {
        if (!static::$activeLoaded) {
            static::$activeMemo = static::where('is_active', true)->first();
            static::$activeLoaded = true;
        }

        return static::$activeMemo;
    }

    /** L'année de contexte de consultation (switch utilisateur), sinon l'active. */
    public static function context(): ?self
    {
        $id = session('annee_contexte_id');
        if ($id) {
            if (!array_key_exists($id, static::$findMemo)) {
                static::$findMemo[$id] = static::find($id);
            }
            if (static::$findMemo[$id]) {
                return static::$findMemo[$id];
            }
        }

        return static::active();
    }

    /**
     * Garde-fou GLOBAL : l'opération est-elle ouverte pour l'année ACTIVE ?
     * (fenêtre cochée « ouverte » ET date du jour dans la période).
     * À utiliser pour toute création/soumission côté usager.
     */
    public static function operationOuverte(string $operation, ?Parcours $parcours = null): bool
    {
        $a = static::active();

        return $a ? $a->operationOpen($operation, $parcours) : false;
    }

    /**
     * Une opération est-elle ouverte ? Sans parcours, seule la règle
     * générale est consultée — c'est le comportement historique.
     * Avec un parcours, la règle la plus précise l'emporte.
     */
    public function operationOpen(string $operation, ?Parcours $parcours = null): bool
    {
        if ($this->statut !== 'ouverte') {
            return false;
        }

        $regle = $this->periodeApplicable($operation, $parcours);

        return $regle ? $regle->ouverteAujourdhui() : false;
    }

    /**
     * Règle qui décide pour ce parcours : la plus précise gagne.
     * Un parcours nommé bat son niveau, qui bat la règle générale.
     * Une seule règle décide — on ne cumule pas, sinon « fermé pour
     * les masters » serait contredit par « ouvert pour tous ».
     */
    public function periodeApplicable(string $operation, ?Parcours $parcours = null): ?Periode
    {
        $candidates = $this->periodes
            ->where('operation', $operation)
            ->filter(function (Periode $p) use ($parcours) {
                if ($p->parcours_id !== null) {
                    return $parcours && (int) $p->parcours_id === (int) $parcours->id;
                }
                if ($p->grade !== null) {
                    return $parcours && $p->grade === $parcours->grade;
                }

                return true;    // règle générale : toujours candidate
            });

        return $candidates->sortByDesc(fn (Periode $p) => $p->precision())->first();
    }

    /** Règle générale d'une opération, celle qui vaut par défaut. */
    public function periodeGenerale(string $operation): ?Periode
    {
        return $this->periodes->first(
            fn (Periode $p) => $p->operation === $operation && $p->estGenerale()
        );
    }

    /** Règles ciblées d'une opération (niveau ou parcours). */
    public function periodesCiblees(string $operation): \Illuminate\Support\Collection
    {
        return $this->periodes
            ->filter(fn (Periode $p) => $p->operation === $operation && !$p->estGenerale())
            ->sortByDesc(fn (Periode $p) => $p->precision())
            ->values();
    }
}
