<?php

namespace App\Models\Concerns;

/**
 * Répare les noms de fichiers dont les accents ont été encodés deux fois.
 *
 * Les fiches générées par le système de l'université arrivent déjà nommées
 * « UniversitÃ© de Kara-… » au lieu de « Université de Kara-… » : le « é »
 * a été encodé en UTF-8, puis relu comme du Latin-1 et réencodé. La
 * corruption précède le téléchargement ; l'application ne peut que la
 * corriger à l'affichage.
 *
 * La réparation ne touche que les noms qui en portent la trace (« Ã »
 * suivi d'un octet de continuation). Un nom sain, accents compris, n'est
 * jamais modifié, et réparer deux fois ne change rien.
 */
trait ReparerNomDeFichier
{
    public static function reparerEncodage(?string $nom): ?string
    {
        if ($nom === null || $nom === '') {
            return $nom;
        }

        if (!preg_match('/\x{00C3}[\x{0080}-\x{00BF}]/u', $nom)) {
            return $nom;
        }

        $repare = mb_convert_encoding($nom, 'ISO-8859-1', 'UTF-8');

        return mb_check_encoding($repare, 'UTF-8') ? $repare : $nom;
    }

    /** Lu partout — écrans, téléchargements, archives — déjà réparé. */
    public function getOriginalFilenameAttribute(?string $valeur): ?string
    {
        return static::reparerEncodage($valeur);
    }

    /** Enregistré réparé : les nouveaux dépôts arrivent propres en base. */
    public function setOriginalFilenameAttribute(?string $valeur): void
    {
        $this->attributes['original_filename'] = static::reparerEncodage($valeur);
    }
}
