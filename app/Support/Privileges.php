<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Qui peut agir sur qui.
 *
 * Les permissions de route disent « cet agent gère les comptes ». Elles
 * ne disent pas LESQUELS, ni quels rôles il peut distribuer. Sans cette
 * seconde règle, un gestionnaire de comptes pouvait cocher
 * « super_admin » en créant un compte — ou en modifiant le sien —,
 * changer le mot de passe d'un super-administrateur, ou se donner le
 * droit de réécrire les rôles : la permission de gérer les comptes
 * valait toutes les autres.
 *
 * Deux niveaux de rigueur :
 *
 *  - Les CLEFS DU SYSTÈME (gérer les comptes, les rôles, les réglages,
 *    forcer un paiement) ne se transmettent que par qui les détient.
 *    Les rôles de travail — agent de dépôt, archiviste… — restent
 *    attribuables par un gestionnaire de comptes : c'est son métier de
 *    créer le compte d'un agent sans être agent lui-même.
 *
 *  - La composition d'un rôle, elle, obéit à la règle stricte : nul ne
 *    met dans un rôle une permission qu'il n'a pas.
 */
class Privileges
{
    /**
     * Les clefs du système : qui les tient peut s'ouvrir tout le reste.
     */
    public const SENSIBLES = [
        'utilisateurs.gerer', 'utilisateurs.creer', 'utilisateurs.editer', 'utilisateurs.supprimer',
        'roles.gerer', 'roles.creer', 'roles.editer', 'roles.supprimer',
        'parametres.gerer',
        'paiements.forcer',
    ];

    /**
     * L'acteur peut-il modifier, réinitialiser ou supprimer ce compte ?
     */
    public static function peutAdministrer(User $acteur, User $cible): bool
    {
        if ($acteur->isSuperAdmin()) {
            return true;
        }

        // Un super-administrateur n'est touché que par un pair.
        if ($cible->isSuperAdmin()) {
            return false;
        }

        // Un compte qui tient une clef que l'acteur n'a pas lui est
        // supérieur : y toucher — changer son mot de passe — serait s'en emparer.
        return self::manquantes($acteur, self::sensiblesParmi(self::permissionsDe($cible)))->isEmpty();
    }

    /**
     * L'acteur peut-il attribuer ce rôle à quelqu'un ?
     */
    public static function peutAttribuerRole(User $acteur, Role|string $role): bool
    {
        if ($acteur->isSuperAdmin()) {
            return true;
        }

        $role = is_string($role) ? Role::where('name', $role)->first() : $role;
        if (!$role || $role->name === 'super_admin') {
            return false;
        }

        return self::manquantes($acteur, self::sensiblesParmi($role->permissions->pluck('name')))->isEmpty();
    }

    /**
     * Permissions que l'acteur n'a pas le droit de mettre dans un rôle
     * (vide = tout est permis). Règle stricte : on ne donne que ce qu'on a.
     *
     * @param  iterable<string>  $permissions
     * @return array<int,string>
     */
    public static function permissionsInterdites(User $acteur, iterable $permissions): array
    {
        if ($acteur->isSuperAdmin()) {
            return [];
        }

        return self::manquantes($acteur, collect($permissions))->values()->all();
    }

    /**
     * Ce compte est-il le dernier super-administrateur actif ?
     * Le supprimer, le désactiver ou lui retirer son rôle fermerait la
     * plateforme à clef, sans personne pour la rouvrir.
     */
    public static function estDernierSuperAdmin(User $cible): bool
    {
        if (!$cible->isSuperAdmin() || !$cible->is_active) {
            return false;
        }

        return User::role('super_admin')
            ->where('is_active', true)
            ->where('id', '!=', $cible->id)
            ->doesntExist();
    }

    /* ── Interne ───────────────────────────────────────── */

    /**
     * Parmi ces permissions, celles que l'acteur ne détient pas.
     * « module.gerer » vaut pour toutes les permissions fines du module :
     * les routes acceptent l'une ou l'autre.
     */
    private static function manquantes(User $acteur, Collection $permissions): Collection
    {
        $miennes = self::permissionsDe($acteur);

        return $permissions->reject(function (string $p) use ($miennes) {
            $module = explode('.', $p)[0];

            return $miennes->contains($p) || $miennes->contains($module . '.gerer');
        });
    }

    private static function sensiblesParmi(Collection $permissions): Collection
    {
        return $permissions->intersect(self::SENSIBLES)->values();
    }

    /** Toutes les permissions effectives d'un compte, par nom. */
    private static function permissionsDe(User $user): Collection
    {
        return $user->getAllPermissions()->pluck('name');
    }
}
