<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une sauvegarde dont le dump échoue produisait une archive d'apparence
 * normale, listée avec un badge vert « Base + pièces ». Elle a bien
 * failli être restaurée sur le serveur. Le manque doit désormais se
 * lire dans le NOM du fichier, qui suit l'archive partout.
 */
class SauvegardeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): \App\Services\SauvegardeService
    {
        return app(\App\Services\SauvegardeService::class);
    }

    public function test_une_archive_sans_base_est_nommee_explicitement(): void
    {
        $this->socle();

        // En test la connexion est SQLite : mysqldump échoue, ce qui est
        // exactement le cas que l'on veut voir traité.
        $resultat = $this->service()->executer(false, 'test');

        $this->assertFalse($resultat['base'],
            "Le dump doit être signalé comme absent.");
        $this->assertNotEmpty($resultat['avertissements'],
            "L'échec doit produire un avertissement.");
        $this->assertStringContainsString('SANS-BASE', $resultat['fichier'],
            "Le nom du fichier doit porter le manque.");

        $chemin = storage_path('app/sauvegardes/' . $resultat['fichier']);
        $this->assertFileExists($chemin);
        @unlink($chemin);
    }

    public function test_la_liste_signale_les_archives_sans_base(): void
    {
        $this->socle();

        $resultat = $this->service()->executer(false, 'test');
        $archives = $this->service()->lister();

        $ligne = collect($archives)->firstWhere('nom', $resultat['fichier']);

        $this->assertNotNull($ligne, "L'archive doit apparaître dans la liste.");
        $this->assertFalse($ligne['base'],
            "La liste doit indiquer que cette archive ne contient pas la base.");

        @unlink($ligne['chemin']);
    }

    public function test_le_manifeste_indique_l_absence_de_base(): void
    {
        $this->socle();

        $resultat = $this->service()->executer(false, 'test');
        $chemin = storage_path('app/sauvegardes/' . $resultat['fichier']);

        $zip = new \ZipArchive();
        $zip->open($chemin);
        $manifeste = $zip->getFromName('MANIFESTE.txt');
        $zip->close();

        $this->assertStringContainsString('ABSENTE', $manifeste,
            "Le manifeste doit annoncer que la base manque.");

        @unlink($chemin);
    }
}
