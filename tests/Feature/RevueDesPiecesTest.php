<?php

namespace Tests\Feature;

use App\Models\AgentPerimetre;
use App\Models\CandidatureFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deux règles, nées d'un cas que l'exploitation avait vu venir :
 *  — refuser une pièce COMMUNE renvoie le candidat refaire son dossier
 *    entier, y compris les parcours déjà acceptés ailleurs. Réservé à
 *    qui détient la permission dédiée.
 *  — une pièce PROPRE à un parcours ne s'examine que par l'agent qui
 *    couvre ce parcours.
 */
class RevueDesPiecesTest extends TestCase
{
    use RefreshDatabase;

    private function piece($candidature, $type, $section = null): CandidatureFile
    {
        return CandidatureFile::create([
            'candidature_id'       => $candidature->id,
            'candidature_choix_id' => $section?->id,
            'request_type_id'      => $type->id,
            'original_filename'    => 'piece.pdf',
            'stored_filename'      => 'piece.pdf',
            'file_path'            => 'candidatures/' . $candidature->id . '/piece.pdf',
            'mime_type'            => 'application/pdf',
            'file_size'            => 1024,
            'statut'               => 'fourni',
        ]);
    }

    public function test_une_piece_sans_section_est_commune(): void
    {
        $this->socle();
        $parcours = $this->parcours();
        $candidature = $this->candidature($parcours);

        $commune = $this->piece($candidature, $this->typeDocument('Acte de naissance'));
        $propre  = $this->piece($candidature, $this->typeDocument('Relevé', $parcours), $candidature->choix->first());

        $this->assertTrue($commune->estCommune());
        $this->assertFalse($propre->estCommune());
    }

    public function test_refuser_une_piece_commune_exige_la_permission(): void
    {
        $this->socle();
        $parcours = $this->parcours();
        $candidature = $this->candidature($parcours);
        $piece = $this->piece($candidature, $this->typeDocument('Acte de naissance'));

        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir', 'candidature.traiter']);

        $this->actingAs($agent->fresh())
            ->post("/admin/candidatures/file/{$piece->id}/revue", [
                'statut' => 'rejete', 'motif_rejet' => 'Illisible', 'confirmer' => 1,
            ]);

        $this->assertNotSame('rejete', $piece->fresh()->statut,
            "Sans la permission dédiée, le refus ne doit pas passer.");
    }

    public function test_avec_la_permission_le_refus_est_accepte(): void
    {
        $this->socle();
        $parcours = $this->parcours();
        $candidature = $this->candidature($parcours);
        $piece = $this->piece($candidature, $this->typeDocument('Acte de naissance'));

        $agent = $this->agent([
            'acceder-backoffice', 'candidatures.voir', 'candidature.traiter',
            'pieces.commune_refuser',
        ]);

        $this->actingAs($agent->fresh())
            ->post("/admin/candidatures/file/{$piece->id}/revue", [
                'statut' => 'rejete', 'motif_rejet' => 'Document illisible', 'confirmer' => 1,
            ]);

        $piece = $piece->fresh();
        $this->assertSame('rejete', $piece->statut);
        $this->assertSame('Document illisible', $piece->motif_rejet);
    }

    public function test_un_refus_sans_motif_ne_casse_pas(): void
    {
        $this->socle();
        $parcours = $this->parcours();
        $candidature = $this->candidature($parcours);
        $piece = $this->piece($candidature, $this->typeDocument('Acte de naissance'));

        $agent = $this->agent([
            'acceder-backoffice', 'candidatures.voir', 'candidature.traiter',
            'pieces.commune_refuser',
        ]);

        // Le champ motif n'est pas transmis du tout : la clé « nullable »
        // manque alors dans les données validées. Cela provoquait un 500.
        $reponse = $this->actingAs($agent->fresh())
            ->post("/admin/candidatures/file/{$piece->id}/revue", [
                'statut' => 'rejete', 'confirmer' => 1,
            ]);

        $this->assertLessThan(500, $reponse->status(),
            "Un refus sans motif doit être refusé proprement, jamais planter.");
        $this->assertNotSame('rejete', $piece->fresh()->statut);
    }

    public function test_une_piece_propre_hors_perimetre_est_refusee(): void
    {
        $this->socle();
        $sien  = $this->parcours('Informatique');
        $autre = $this->parcours('Gestion');

        $candidature = $this->candidature($autre, 'AILLEURS');
        $type = $this->typeDocument('Relevé de notes', $autre);
        $piece = $this->piece($candidature, $type, $candidature->choix->first());

        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir', 'candidature.traiter']);
        AgentPerimetre::create(['user_id' => $agent->id, 'parcours_id' => $sien->id]);

        $this->actingAs($agent->fresh())
            ->get("/admin/candidatures/file/{$piece->id}/preview")
            ->assertForbidden();
    }
}
