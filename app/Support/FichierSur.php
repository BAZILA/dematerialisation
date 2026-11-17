<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Ce qu'on garde d'un fichier envoyé — et ce qu'on refuse de croire.
 *
 * Tout ce que le navigateur déclare d'un fichier est choisi par celui
 * qui l'envoie : son nom, son extension, son type. Seul le CONTENU fait
 * foi. Le nom de stockage et son extension sont donc déduits du contenu,
 * jamais du nom reçu : « facture.php » dont le contenu est un PDF est
 * rangé en « .pdf ».
 */
class FichierSur
{
    /** Les seuls contenus acceptés, et l'extension que chacun reçoit. */
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];

    /** Type réel du fichier, lu dans son contenu. Null s'il n'est pas admis. */
    public static function type(UploadedFile $file): ?string
    {
        $mime = $file->getMimeType();

        return isset(self::TYPES[$mime]) ? $mime : null;
    }

    /**
     * Nom sous lequel ranger le fichier sur le disque.
     * Horodatage + aléa (deux envois dans la même seconde ne s'écrasent
     * pas) + nom assaini + extension tirée du contenu.
     */
    public static function nomDeStockage(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($base));
        $base = trim((string) $base, '._-') ?: 'document';

        $ext = self::TYPES[self::type($file)] ?? 'bin';

        return time() . '_' . Str::random(8) . '_' . substr($base, 0, 90) . '.' . $ext;
    }

    /**
     * Nom d'origine, gardé pour l'affichage et le téléchargement.
     * Débarrassé des caractères de contrôle et des séparateurs de chemin,
     * borné en longueur, et coiffé de l'extension qui correspond au
     * contenu — pour qu'un fichier téléchargé s'ouvre avec le bon logiciel.
     */
    public static function nomOriginal(UploadedFile $file): string
    {
        $nom = $file->getClientOriginalName();
        $nom = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $nom) ?? '';
        $nom = trim(preg_replace('/\s+/u', ' ', $nom) ?? '');

        $base = pathinfo($nom, PATHINFO_FILENAME);
        $base = trim(mb_substr($base, 0, 150)) ?: 'document';

        $ext = self::TYPES[self::type($file)] ?? strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', (string) $ext);

        return $base . ($ext ? '.' . $ext : '');
    }

    /**
     * En-têtes pour servir une pièce dans le navigateur.
     *
     * Le nom du fichier vient de l'usager : collé tel quel entre
     * guillemets dans l'en-tête, un guillemet suffisait à le casser.
     * Et un type inattendu est servi en téléchargement, jamais affiché :
     * le navigateur n'a pas à interpréter ce qu'on ne connaît pas.
     *
     * @return array<string,string>
     */
    public static function entetesApercu(?string $mime, ?string $nom): array
    {
        $connu = isset(self::TYPES[$mime]);

        return [
            'Content-Type'           => $connu ? $mime : 'application/octet-stream',
            'Content-Disposition'    => self::disposition($connu ? 'inline' : 'attachment', $nom),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /** En-tête Content-Disposition correctement échappé, accents compris. */
    public static function disposition(string $mode, ?string $nom): string
    {
        $nom = trim(str_replace(['/', '\\'], '-', (string) $nom)) ?: 'document';

        $repli = preg_replace('/[^\x20-\x7E]/', '_', Str::ascii($nom));
        $repli = str_replace('%', '_', (string) $repli) ?: 'document';

        return HeaderUtils::makeDisposition($mode, $nom, $repli);
    }
}
