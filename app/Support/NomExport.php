<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Nom de fichier lisible pour un export : nature du contenu + contexte
 * (année, filtres actifs) + horodatage. Un fichier reçu par mail ou
 * retrouvé trois mois plus tard doit se comprendre par son seul nom,
 * sans avoir à l'ouvrir.
 */
class NomExport
{
    /**
     * @param  string       $base       nature du contenu ("depots", "candidatures"…)
     * @param  array<mixed> $contexte   segments de contexte, dans l'ordre ; les vides sont ignorés
     * @param  string       $extension  sans le point ("xlsx", "csv", "pdf", "zip")
     */
    public static function build(string $base, array $contexte, string $extension): string
    {
        $segments = [];
        foreach ($contexte as $valeur) {
            if ($valeur === null || $valeur === '') {
                continue;
            }
            $segments[] = Str::slug((string) $valeur, '-');
        }

        $segments[] = now()->format('Ymd-His');

        return $base . '_' . implode('_', $segments) . '.' . $extension;
    }
}
