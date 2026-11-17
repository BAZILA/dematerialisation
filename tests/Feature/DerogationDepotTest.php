<?php

namespace Tests\Feature;

use App\Models\Bachelier;
use App\Models\Derogation;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Un bachelier n'a pas encore son certificat de nationalité : le
 * document met des semaines à sortir, la période de dépôt se ferme
 * avant. Il s'engage à le fournir plus tard, son dossier avance.
 *
 * La pièce reste DUE : la dérogation la diffère, elle ne l'annule pas.
 */
class DerogationDepotTest extends TestCase
{
    use RefreshDatabase;

    private function etudiant(): User
    {
        $bachelier = Bachelier::create([
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
            'annee_bac' => '2026', 'is_registered' => true,
        ]);

        $user = User::create([
            'numero_identifiant' => 'KADJO', 'email' => 'kadjo@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        Student::create([
            'user_id' => $user->id, 'bachelier_id' => $bachelier->id,
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        return $user->fresh();
    }

    private ?\App\Models\Parcours $parcoursTest = null;

    /** Le parcours porte le niveau, et le niveau decide des pieces. */
    private function parcoursTest(): \App\Models\Parcours
    {
        return $this->parcoursTest ??= $this->parcours();
    }

    private function typeDepot(string $libelle, bool $derogeable = false): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'depot',
            'grade' => $this->parcoursTest()->grade,
            'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => true,
            'derogation_possible' => $derogeable,
        ]);
    }

    private function depot(User $user): StudentRequest
    {
        $annee = \App\Models\AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
             'statut' => 'ouverte', 'is_active' => true]
        );
        \App\Models\Periode::updateOrCreate(
            ['annee_academique_id' => $annee->id, 'operation' => 'depot',
             'grade' => null, 'parcours_id' => null],
            ['is_open' => true]
        );

        return StudentRequest::create([
            'student_id' => $user->student->id, 'category' => 'depot',
            'parcours_id' => $this->parcoursTest()->id,
            'annee_academique_id' => $annee->id, 'status' => 'brouillon', 'version' => 1,
        ]);
    }

    private function soumettre(User $user, StudentRequest $sr, array $derogations = [])
    {
        return $this->actingAs($user)->post('/student/requests/submit', [
            'student_request_id' => $sr->id,
            'engagement' => 1,
            'derogations' => $derogations,
        ]);
    }

    /* ── Paramétrage ────────────────────────────────── */

    public function test_aucune_piece_n_est_derogeable_par_defaut(): void
    {
        $this->socle();
        $type = $this->typeDepot('Certificat de nationalité');

        $this->assertFalse((bool) $type->derogation_possible,
            "Le report doit être un choix explicite de l'administration.");
    }

    public function test_une_piece_non_derogeable_bloque_toujours(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $this->typeDepot('Fiche de renseignements');     // non dérogeable
        $sr = $this->depot($user);

        $this->soumettre($user, $sr, [])->assertSessionHasErrors();

        $this->assertSame('brouillon', $sr->fresh()->status,
            "Une pièce non ouverte au report reste bloquante, comme avant.");
    }

    /* ── Engagement ─────────────────────────────────── */

    public function test_l_etudiant_peut_s_engager_et_deposer(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);

        $this->soumettre($user, $sr, [$nationalite->id]);

        $sr = $sr->fresh(['derogations']);
        $this->assertSame('soumis', $sr->status,
            "Le dossier doit avancer malgré la pièce manquante.");
        $this->assertTrue($sr->sousDerogation());
        $this->assertDatabaseHas('derogations', [
            'student_request_id' => $sr->id,
            'request_type_id'    => $nationalite->id,
            'statut'             => 'engagee',
        ]);
    }

    public function test_on_ne_peut_pas_deroger_a_une_piece_non_autorisee(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $bloquante = $this->typeDepot('Fiche de renseignements');   // non dérogeable
        $sr = $this->depot($user);

        // Requête forgée : on tente de déroger quand même
        $this->soumettre($user, $sr, [$bloquante->id])->assertSessionHasErrors();

        $this->assertSame('brouillon', $sr->fresh()->status);
        $this->assertSame(0, Derogation::count(),
            "Aucune dérogation ne doit être créée sur une pièce fermée au report.");
    }

    public function test_une_piece_bloquante_resiste_meme_avec_une_derogation_valide(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $this->typeDepot('Fiche de renseignements');   // bloquante
        $sr = $this->depot($user);

        $this->soumettre($user, $sr, [$nationalite->id])->assertSessionHasErrors();

        $this->assertSame('brouillon', $sr->fresh()->status,
            "Déroger une pièce ne dispense pas des autres.");
    }

    /* ── Levée par l'agent ──────────────────────────── */

    public function test_l_agent_leve_la_derogation_quand_la_piece_arrive(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);
        $this->soumettre($user, $sr, [$nationalite->id]);

        $derogation = Derogation::first();
        $agent = $this->agent(['acceder-backoffice', 'depot.traiter', 'dossiers.consulter']);

        $this->actingAs($agent)->post("/admin/derogations/{$derogation->id}/lever");

        $derogation = $derogation->fresh();
        $this->assertSame('levee', $derogation->statut);
        $this->assertSame($agent->id, $derogation->levee_par);
        $this->assertNotNull($derogation->levee_at);
        $this->assertFalse($sr->fresh()->sousDerogation(),
            "Une fois la pièce fournie, le dossier n'est plus sous dérogation.");
    }

    public function test_une_derogation_deja_levee_ne_se_leve_pas_deux_fois(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);
        $this->soumettre($user, $sr, [$nationalite->id]);

        $derogation = Derogation::first();
        $agent = $this->agent(['acceder-backoffice', 'depot.traiter', 'dossiers.consulter']);

        $this->actingAs($agent)->post("/admin/derogations/{$derogation->id}/lever");
        $premiere = $derogation->fresh()->levee_at;

        $this->actingAs($agent)->post("/admin/derogations/{$derogation->id}/lever")
            ->assertSessionHasErrors();

        $this->assertEquals($premiere, $derogation->fresh()->levee_at);
    }

    /* ── Traçabilité ────────────────────────────────── */

    public function test_l_engagement_est_journalise(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);

        $this->soumettre($user, $sr, [$nationalite->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'depot.derogation']);
    }

    public function test_la_levee_est_journalisee(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);
        $this->soumettre($user, $sr, [$nationalite->id]);

        $agent = $this->agent(['acceder-backoffice', 'depot.traiter', 'dossiers.consulter']);
        $this->actingAs($agent)->post('/admin/derogations/' . Derogation::first()->id . '/lever');

        $this->assertDatabaseHas('audit_logs', ['action' => 'depot.derogation_levee']);
    }

    /* ── Non-régression ─────────────────────────────── */

    public function test_un_dossier_complet_se_soumet_comme_avant(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $sr = $this->depot($user);   // aucune pièce obligatoire définie

        $this->soumettre($user, $sr, []);

        $sr = $sr->fresh();
        $this->assertSame('soumis', $sr->status);
        $this->assertFalse($sr->sousDerogation(),
            "Sans pièce manquante, aucune dérogation ne doit apparaître.");
    }
}
