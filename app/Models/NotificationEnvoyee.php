<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une notification e-mail ou SMS, avec son sort réel. */
class NotificationEnvoyee extends Model
{
    protected $table = 'notifications_envoyees';

    protected $fillable = [
        'canal', 'evenement', 'concerne_type', 'concerne_id', 'user_id',
        'destinataire', 'sujet', 'message',
        'statut', 'erreur', 'fournisseur', 'reference_externe', 'tentatives', 'envoye_at',
    ];

    protected $casts = ['envoye_at' => 'datetime', 'tentatives' => 'integer'];

    public static array $statuts = [
        'en_attente' => 'En attente',
        'envoye'     => 'Envoyée',
        'echec'      => 'Échec',
        'suspendu'   => 'Suspendue (module désactivé)',
    ];

    public static array $canaux = ['email' => 'E-mail', 'sms' => 'SMS'];

    public function concerne()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatutBadgeAttribute(): string
    {
        return match ($this->statut) {
            'envoye'   => 'success',
            'echec'    => 'danger',
            'suspendu' => 'secondary',
            default    => 'warning',
        };
    }

    /** Le module de notification est-il actif ? (interrupteur général) */
    public static function moduleActif(): bool
    {
        return (bool) Setting::get('notifications.enabled', false);
    }

    public static function canalActif(string $canal): bool
    {
        return self::moduleActif()
            && (bool) Setting::get("notifications.{$canal}.enabled", false);
    }
}
