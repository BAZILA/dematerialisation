<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Journal d'audit : trace des actions sensibles (validations, rejets,
 * décisions, téléchargements, changements de configuration…).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // journal immuable : pas de updated_at

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'meta', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    /**
     * Enregistre une entrée d'audit. Ne lève jamais d'exception
     * (l'audit ne doit pas casser l'action métier).
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $meta = []
    ): void {
        try {
            $req = request();
            static::create([
                'user_id'      => Auth::id(),
                'action'       => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id'   => $subject?->getKey(),
                'description'  => $description,
                'meta'         => $meta ?: null,
                'ip_address'   => $req?->ip(),
                'user_agent'   => $req?->userAgent(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AuditLog: ' . $e->getMessage());
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Libellés lisibles des actions. */
    public static array $labels = [
        'auth.login'               => 'Connexion',
        'auth.logout'              => 'Déconnexion',
        'depot.valide'             => 'Dépôt validé',
        'demande.validee'          => 'Demande validée',
        'demande.correction'       => 'Renvoyée en correction',
        'demande.rejetee'          => 'Demande rejetée',
        'demande.rouverte'         => 'Validation annulée (rouverte)',
        'candidature.decision'     => 'Décision de candidature',
        'candidature.completee'    => 'Candidature complétée (candidat)',
        'archive.fiche'            => 'Téléchargement de fiche',
        'archive.dossier'          => 'Téléchargement de dossier',
        'archive.lot'              => 'Téléchargement de lot',
        'stats.export'             => 'Export statistiques',
        'etudiant.migre_ancien'    => 'Étudiant migré nouveau → ancien',
        'utilisateur.cree'         => 'Utilisateur créé',
        'utilisateur.modifie'      => 'Utilisateur modifié',
        'utilisateur.supprime'     => 'Utilisateur supprimé',
        'role.cree'                => 'Rôle créé',
        'role.modifie'             => 'Rôle modifié',
        'role.supprime'            => 'Rôle supprimé',
        '2fa.activee'              => '2FA activée',
        '2fa.desactivee'           => '2FA désactivée',
        '2fa.reinitialisee'        => '2FA réinitialisée',
        '2fa.echec'                => 'Code 2FA incorrect',
        'perimetre.affecte'        => 'Périmètre affecté',
        'perimetre.retire'         => 'Périmètre retiré',
        'parametres.modifie'       => 'Paramètres modifiés',
        'annee.activee'            => 'Année activée',
        'etudiants.bascule_anciens' => 'Nouveaux basculés en anciens',
        'annee.creee'              => 'Année créée',
    ];

    public function getActionLabelAttribute(): string
    {
        return static::$labels[$this->action] ?? $this->action;
    }
}
