<?php

/**
 * =====================================================================
 *  Nettoyage des fichiers orphelins — a lancer APRES remise-a-zero-des-tests.sql
 * =====================================================================
 *
 *  Le script SQL efface les LIGNES en base. Les fichiers deposes, eux,
 *  restent sur le disque. Ce script supprime les fichiers qu'AUCUNE ligne
 *  ne reference plus.
 *
 *  REGLE : un fichier n'est supprime que s'il n'est reference nulle part.
 *  Un vrai depot reste reference en base : il n'est jamais touche, quelle
 *  que soit sa date. Il n'y a donc aucune limite dans le temps a regler.
 *
 *  PERIMETRE : uniquement les dossiers de pieces deposees
 *      storage/app/private/requests/
 *      storage/app/private/candidatures/
 *  Jamais : signatures du personnel, cachet, sauvegardes, gabarits.
 *
 *  PAR DEFAUT, IL N'EFFACE RIEN : il affiche ce qu'il effacerait.
 *
 *  UTILISATION (depuis la racine du site) :
 *      $PHP nettoyer-fichiers-orphelins.php              # a blanc
 *      $PHP nettoyer-fichiers-orphelins.php --supprimer  # pour de vrai
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ligne de commande uniquement.\n");
}

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require_once __DIR__ . '/nettoyer-fichiers-orphelins.lib.php';

$pourDeVrai = in_array('--supprimer', $argv, true);

exit(ukNettoyerOrphelins(storage_path('app/private'), $pourDeVrai));
