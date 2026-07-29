<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Paiement des frais d'un dossier.
 *
 * Cycle : en_attente (créé avec le dossier) → soumis (l'usager a déclaré
 * sa transaction) → valide / rejete (rapprochement par un agent).
 */
class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'reference', 'payable_type', 'payable_id', 'procedure_code', 'annee_academique_id',
        'montant', 'devise', 'operateur', 'numero_payeur', 'transaction_id', 'declare_at',
        'statut', 'motif_rejet', 'valide_par', 'valide_at', 'verifie_auto', 'meta',
    ];

    protected $casts = [
        'declare_at'   => 'datetime',
        'valide_at'    => 'datetime',
        'verifie_auto' => 'boolean',
        'meta'         => 'array',
        'montant'      => 'integer',
    ];

    public static array $statuts = [
        'en_attente' => 'En attente de paiement',
        'soumis'     => 'Déclaré — à vérifier',
        'valide'     => 'Payé',
        'rejete'     => 'Rejeté',
        'rembourse'  => 'Remboursé',
    ];

    public static array $operateurs = [
        'tmoney'   => 'T-Money',
        'flooz'    => 'Flooz',
        'especes'  => 'Espèces (guichet)',
        'virement' => 'Virement bancaire',
    ];

    /**
     * Interrupteur général du module.
     *
     * Éteint, TOUT ce qui touche au paiement disparaît : entrée de menu,
     * écrans agents, encadrés côté usager, frais dans les procédures,
     * groupe de réglages. Les enregistrements restent en base et
     * réapparaissent tels quels à la réactivation.
     */
    public static function moduleActif(): bool
    {
        return (bool) Setting::get('payments.enabled', false);
    }

    /* ── Relations ─────────────────────────────────────── */

    public function payable()
    {
        return $this->morphTo();
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /* ── États ─────────────────────────────────────────── */

    public function estPaye(): bool
    {
        return $this->statut === 'valide';
    }

    public function estEnAttenteDeclaration(): bool
    {
        return $this->statut === 'en_attente';
    }

    /** L'usager peut-il (re)déclarer une transaction ? */
    public function peutDeclarer(): bool
    {
        return in_array($this->statut, ['en_attente', 'rejete'], true);
    }

    /* ── Accesseurs ────────────────────────────────────── */

    public function getStatutLabelAttribute(): string
    {
        return self::$statuts[$this->statut] ?? $this->statut;
    }

    public function getStatutBadgeAttribute(): string
    {
        return match ($this->statut) {
            'valide'     => 'success',
            'soumis'     => 'warning',
            'rejete'     => 'danger',
            'rembourse'  => 'info',
            default      => 'secondary',
        };
    }

    public function getOperateurLabelAttribute(): string
    {
        return self::$operateurs[$this->operateur] ?? $this->operateur;
    }

    /** Montant formaté : « 5 000 FCFA ». */
    public function getMontantFormateAttribute(): string
    {
        return number_format($this->montant, 0, ',', ' ') . ' ' . self::libelleDevise($this->devise);
    }

    public static function libelleDevise(?string $code): string
    {
        return match ($code) {
            'XOF', null => 'FCFA',
            default     => $code,
        };
    }

    /** Référence unique, lisible et dictable au téléphone. */
    public static function genererReference(): string
    {
        do {
            $ref = 'PAY-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }
}
