<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Règles de recyclage paramétrables (super-admin).
 * Le recyclage = réinscription d'un étudiant après interruption, admise
 * jusqu'à N années d'ancienneté (depuis le BAC / la 1re inscription),
 * éventuellement soumise à dérogation.
 */
class RecyclageRule
{
    public static function enabled(): bool
    {
        return (bool) Setting::get('recyclage.enabled', true);
    }

    public static function maxAnnees(): int
    {
        return (int) Setting::get('recyclage.max_annees', 10);
    }

    public static function derogationRequise(): bool
    {
        return (bool) Setting::get('recyclage.derogation_requise', true);
    }

    /** Années écoulées depuis l'année de référence (BAC / 1re inscription). */
    public static function ancienneteAnnees(?int $anneeReference, ?int $anneeCourante = null): ?int
    {
        if (!$anneeReference) {
            return null;
        }
        $anneeCourante ??= (int) now()->year;

        return max(0, $anneeCourante - $anneeReference);
    }

    /**
     * L'étudiant est-il dans la limite de recyclage ?
     * - recyclage désactivé → false ;
     * - année de référence inconnue → true (on ne bloque pas faute de donnée) ;
     * - sinon : ancienneté ≤ max.
     */
    public static function estEligible(?int $anneeReference, ?int $anneeCourante = null): bool
    {
        if (!static::enabled()) {
            return false;
        }
        $anc = static::ancienneteAnnees($anneeReference, $anneeCourante);
        if ($anc === null) {
            return true;
        }

        return $anc <= static::maxAnnees();
    }

    /** Résumé lisible pour l'affichage. */
    public static function resume(): string
    {
        if (!static::enabled()) {
            return 'Recyclage désactivé.';
        }
        $s = 'Recyclage admis jusqu\'à ' . static::maxAnnees() . ' an(s) d\'ancienneté';
        $s .= static::derogationRequise() ? ', sur dérogation.' : '.';

        return $s;
    }
}
