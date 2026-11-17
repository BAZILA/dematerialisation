<?php

namespace App\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Empêche qu'une donnée saisie par un usager devienne une FORMULE dans
 * un export.
 *
 * Un tableur traite comme formule toute cellule qui commence par « = »
 * (et, à l'ouverture d'un CSV, par « + », « - » ou « @ »). Or les
 * exports reprennent des champs que n'importe qui peut remplir : le
 * formulaire de candidature est public. Un « nom » valant
 * =HYPERLINK("http://…";"Cliquez") ou une formule DDE s'exécuterait sur
 * le poste de l'agent qui ouvre l'export.
 *
 * Ces valeurs sont écrites comme du TEXTE, précédées d'une apostrophe :
 * le tableur les affiche au lieu de les calculer. Les numéros de
 * téléphone (« +228 90 00 00 00 ») et les nombres négatifs, qui ne
 * peuvent rien exécuter, sont laissés intacts.
 */
class LiaisonExcelSure extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && self::estDangereuse($value)) {
            $cell->setValueExplicit("'" . $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public static function estDangereuse(string $valeur): bool
    {
        $v = ltrim($valeur, " \n");
        if ($v === '') {
            return false;
        }

        $premier = $v[0];

        if (in_array($premier, ['=', '@', "\t", "\r"], true)) {
            return true;
        }

        // « + » et « - » : dangereux seulement s'il y a autre chose que
        // des chiffres et de la ponctuation de numéro derrière.
        if ($premier === '+' || $premier === '-') {
            return !preg_match('/^[+\-][\d\s().,\/\-]*$/', $v);
        }

        return false;
    }
}
