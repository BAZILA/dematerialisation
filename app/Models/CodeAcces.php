<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Code d'accès à usage unique remis à un usager après vérification
 * de son identité. Stocké haché, périssable, consommé à la première
 * utilisation réussie.
 */
class CodeAcces extends Model
{
    protected $table = 'codes_acces';

    protected $fillable = [
        'user_id', 'code_hash', 'expire_at', 'utilise_at',
        'tentatives', 'canal', 'ip',
    ];

    protected $casts = [
        'expire_at'  => 'datetime',
        'utilise_at' => 'datetime',
        'tentatives' => 'integer',
    ];

    /** Au-delà, le code est brûlé : on ne laisse pas essayer indéfiniment. */
    public const MAX_TENTATIVES = 5;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estExpire(): bool
    {
        return $this->expire_at->isPast();
    }

    public function estConsomme(): bool
    {
        return $this->utilise_at !== null;
    }

    public function estBrule(): bool
    {
        return $this->tentatives >= self::MAX_TENTATIVES;
    }

    /** Utilisable = ni consommé, ni expiré, ni brûlé par les essais. */
    public function estUtilisable(): bool
    {
        return !$this->estConsomme() && !$this->estExpire() && !$this->estBrule();
    }

    /** Minutes restantes avant péremption, plancher à zéro. */
    public function minutesRestantes(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->expire_at, false) / 60));
    }
}
