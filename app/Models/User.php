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
        'email',
        'password',
        'role',           // rôle principal dénormalisé (affichage / redirection rapide)
        'type_etudiant',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
    ];

    public function student()
    {
        return $this->hasOne(Student::class);
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
}
