<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Les exigences de mot de passe, à un seul endroit.
 *
 * Elles étaient écrites à la main dans chaque contrôleur (« min:8 »),
 * donc impossibles à durcir d'un coup — et « 12345678 » passait.
 */
class MotDePasse
{
    /** Usagers : étudiants et bacheliers. Exigeant sans être décourageant. */
    public static function usager(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    /**
     * Personnel : ces comptes ouvrent les pièces d'identité et les
     * diplômes de tous les étudiants. Le niveau d'exigence suit.
     */
    public static function personnel(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers();
    }

    /** La règle qui convient à ce compte. */
    public static function pour(\App\Models\User $user): Password
    {
        return $user->isStaff() ? self::personnel() : self::usager();
    }
}
