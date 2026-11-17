<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * DATE_FORMAT et TIMESTAMPDIFF sont propres à MySQL.
 *
 * La suite de tests tourne sur SQLite : tant que ces fonctions étaient
 * écrites en dur, le tableau de bord et les statistiques — les deux
 * écrans les plus consultés — ne pouvaient tout simplement pas être
 * ouverts par un test. Une panne y serait passée inaperçue.
 *
 * Sur MySQL le SQL produit est identique au précédent, au caractère
 * près : la production ne change pas de comportement.
 */
class SqlPortable
{
    /** Mois au format « AAAA-MM » extrait d'une colonne date. */
    public static function mois(string $colonne): string
    {
        $colonne = self::colonne($colonne);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$colonne})",
            'pgsql'  => "to_char({$colonne}, 'YYYY-MM')",
            default  => "DATE_FORMAT({$colonne}, '%Y-%m')",
        };
    }

    /** Écart en heures entre deux colonnes datetime. */
    public static function ecartHeures(string $debut, string $fin): string
    {
        $debut = self::colonne($debut);
        $fin   = self::colonne($fin);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday({$fin}) - julianday({$debut})) * 24",
            'pgsql'  => "EXTRACT(EPOCH FROM ({$fin} - {$debut})) / 3600",
            default  => "TIMESTAMPDIFF(HOUR, {$debut}, {$fin})",
        };
    }

    /**
     * Ces fragments partent en SQL brut. Les appels du projet ne passent
     * que des noms écrits en dur, mais on refuse tout de même ce qui
     * n'est pas un nom de colonne : une garde qui ne coûte rien.
     */
    private static function colonne(string $nom): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $nom)) {
            throw new \InvalidArgumentException("Nom de colonne invalide : {$nom}");
        }

        return $nom;
    }
}
