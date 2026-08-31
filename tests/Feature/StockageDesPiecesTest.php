<?php

namespace Tests\Feature;

use App\Models\CandidatureFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ces deux défauts ont réellement détruit trois pièces d'un dossier :
 * deux fichiers déposés dans la même seconde s'écrasaient, et un
 * remplacement effaçait le fichier encore utilisé par une autre pièce.
 * Ils ne doivent pas revenir.
 */
class StockageDesPiecesTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'Scan identique.pdf',
            "%PDF-1.4\n% {$contenu}\n%%EOF\n"
        );
    }

    private function ranger($candidature, UploadedFile $fichier, ?int $typeId = null): CandidatureFile
    {
        $controleur = app(\App\Http\Controllers\PublicCandidatureController::class);
        $methode = new \ReflectionMethod($controleur, 'rangerPiece');
        $methode->setAccessible(true);
        $typeId ??= $this->typeDocument('Piece ' . uniqid())->id;

        return $methode->invoke($controleur, $candidature, $fichier, $typeId, null);
    }

    public function test_deux_pieces_de_meme_nom_ne_s_ecrasent_pas(): void
    {
        $this->socle();
        $candidature = $this->candidature($this->parcours());

        $pieces = [];
        foreach (['un', 'deux', 'trois'] as $marqueur) {
            $pieces[] = $this->ranger($candidature, $this->pdf($marqueur));
        }

        $chemins = array_map(fn ($p) => $p->file_path, $pieces);
        $this->assertCount(3, array_unique($chemins),
            'Trois dépôts doivent donner trois chemins distincts.');

        foreach ($pieces as $piece) {
            $this->assertTrue(Storage::disk('local')->exists($piece->file_path),
                "Le fichier {$piece->file_path} devrait exister.");
        }

        $contenus = array_map(fn ($p) => Storage::disk('local')->get($p->file_path), $pieces);
        $this->assertCount(3, array_unique($contenus),
            'Aucun contenu ne doit avoir été écrasé par un autre.');
    }

    public function test_un_fichier_partage_n_est_pas_efface(): void
    {
        $this->socle();
        $candidature = $this->candidature($this->parcours());

        $originale = $this->ranger($candidature, $this->pdf('originale'));

        // Deuxième enregistrement pointant volontairement le même fichier
        $doublon = CandidatureFile::create([
            'candidature_id'    => $candidature->id,
            'request_type_id'   => $this->typeDocument('Autre piece')->id,
            'original_filename' => $originale->original_filename,
            'stored_filename'   => $originale->stored_filename,
            'file_path'         => $originale->file_path,
            'mime_type'         => 'application/pdf',
            'file_size'         => 20,
        ]);

        $controleur = app(\App\Http\Controllers\PublicCandidatureController::class);
        $supprimer = new \ReflectionMethod($controleur, 'supprimerFichierPhysique');
        $supprimer->setAccessible(true);

        $supprimer->invoke($controleur, $doublon);
        $this->assertTrue(Storage::disk('local')->exists($originale->file_path),
            "Le fichier est encore utilisé par une autre pièce : il doit rester.");

        $doublon->delete();
        $supprimer->invoke($controleur, $originale);
        $this->assertFalse(Storage::disk('local')->exists($originale->file_path),
            "Plus personne ne l'utilise : il doit être effacé.");
    }

    public function test_une_piece_sans_fichier_est_signalee(): void
    {
        $this->socle();
        $candidature = $this->candidature($this->parcours());
        $piece = $this->ranger($candidature, $this->pdf('a-effacer'));

        $this->assertTrue($piece->fichierPresent());

        Storage::disk('local')->delete($piece->file_path);
        $this->assertFalse($piece->fresh()->fichierPresent(),
            "Une pièce dont le fichier a disparu doit se déclarer absente.");
    }
}
