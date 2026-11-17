<?php

/*
|--------------------------------------------------------------------------
| Exports Excel / CSV
|--------------------------------------------------------------------------
|
| Seule la liaison des valeurs est redéfinie ; tout le reste garde la
| configuration par défaut du paquet (fusionnée au démarrage).
|
| Voir App\Support\LiaisonExcelSure : une donnée saisie par un usager ne
| doit jamais être interprétée comme une formule par le tableur de
| l'agent qui ouvre l'export.
|
*/

return [
    'value_binder' => [
        'default' => \App\Support\LiaisonExcelSure::class,
    ],
];
