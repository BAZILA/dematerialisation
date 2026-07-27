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
    public static function operationOuverte(string $operation): bool
    {
        $a = static::active();

        return $a ? $a->operationOpen($operation) : false;
    }

    /** Une opération est-elle actuellement ouverte pour cette année ? */
    public function operationOpen(string $operation): bool
    {
        if ($this->statut !== 'ouverte') {
            return false;
        }
        $p = $this->periodes->firstWhere('operation', $operation);
        if (!$p || !$p->is_open) {
            return false;
        }
        $today = now()->startOfDay();
        if ($p->date_debut && $today->lt($p->date_debut)) {
            return false;
        }
        if ($p->date_fin && $today->gt($p->date_fin->copy()->endOfDay())) {
            return false;
        }
        return true;
    }
}
