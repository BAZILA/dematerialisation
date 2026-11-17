<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dossier des archives de sauvegarde
    |--------------------------------------------------------------------------
    | Vide = storage/app/sauvegardes. On peut le déplacer hors de
    | l'arborescence web sur un hébergement mutualisé, et les tests le
    | redirigent vers un dossier jetable : le service applique une purge
    | de rétention, qui ne doit jamais s'exécuter sur les vraies archives.
    */

    'dossier' => env('SAUVEGARDE_DOSSIER'),

];
