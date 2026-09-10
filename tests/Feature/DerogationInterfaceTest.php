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
 * La logique de dérogation est couverte par DerogationDepotTest.
 * Ici on vérifie que les ÉCRANS l'exposent : la case d'engagement au
 * dépôt, le bloc de levée côté agent, le réglage pièce par pièce, et
 * la mention qui doit suivre le dossier jusque dans les archives.
 */
class DerogationInterfaceTest extends TestCase
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
            'grade' => $this->parcoursTest()->grade, 'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => true,
            'derogation_possible' => $derogeable,
        ]);
    }

    private function depot(User $user, string $statut = 'brouillon'): StudentRequest
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
            'annee_academique_id' => $annee->id, 'status' => $statut, 'version' => 1,
        ]);
    }

    /* ── Écran de dépôt (usager) ─────────────────────── */

    public function test_la_case_de_report_apparait_pour_une_piece_ouverte(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $this->depot($user);

        $this->actingAs($user)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Pièces à fournir plus tard')
            ->assertSee('name="derogations[]"', false)
            ->assertSee('value="' . $nationalite->id . '"', false);
    }

    public function test_aucune_case_de_report_sur_une_piece_fermee(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $this->typeDepot('Fiche de renseignements');   // non dérogeable
        $this->depot($user);

        $reponse = $this->actingAs($user)->get('/student/dashboard')->assertOk();

        $reponse->assertDontSee('Pièces à fournir plus tard');
        // Pas « name="derogations[]" » : la chaîne figure aussi dans le script
        // qui pilote le bouton. On vise la case elle-même.
        $reponse->assertDontSee('id="derog-', false);
    }

    public function test_une_piece_fermee_nomme_ce_qui_bloque(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $this->typeDepot('Fiche de renseignements');
        $this->depot($user);

        $this->actingAs($user)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Pièces encore exigées')
            ->assertSee('Fiche de renseignements');
    }

    public function test_le_bouton_reste_verrouille_tant_que_rien_n_est_coche(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user);

        $this->actingAs($user)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('id="submitBtn-' . $sr->id . '"', false)
            ->assertSee('disabled', false);
    }

    /* ── Écran de traitement (agent) ─────────────────── */

    private function agentTraitant(): User
    {
        return $this->agent([
            'acceder-backoffice', 'depot.traiter', 'dossiers.consulter', 'demandes.voir',
        ]);
    }

    public function test_l_agent_voit_le_bloc_et_le_bouton_de_levee(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user, 'soumis');

        $derogation = Derogation::create([
            'student_request_id' => $sr->id,
            'request_type_id'    => $nationalite->id,
            'statut'             => 'engagee',
            'engagee_at'         => now(),
        ]);

        $this->actingAs($this->agentTraitant())
            ->get("/admin/requests/detail/{$sr->id}")
            ->assertOk()
            ->assertSee('Pièces à fournir plus tard')
            ->assertSee('Certificat de nationalité')
            ->assertSee('Pièce reçue')
            ->assertSee(route('admin.derogations.lever', $derogation), false);
    }

    public function test_le_bloc_disparait_quand_aucune_piece_n_est_differee(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $this->typeDepot('Fiche de renseignements');
        $sr = $this->depot($user, 'soumis');

        $this->actingAs($this->agentTraitant())
            ->get("/admin/requests/detail/{$sr->id}")
            ->assertOk()
            ->assertDontSee('Pièces à fournir plus tard');
    }

    public function test_une_derogation_levee_n_offre_plus_le_bouton(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user, 'soumis');

        Derogation::create([
            'student_request_id' => $sr->id,
            'request_type_id'    => $nationalite->id,
            'statut'             => 'levee',
            'engagee_at'         => now()->subDays(3),
            'levee_at'           => now(),
        ]);

        $reponse = $this->actingAs($this->agentTraitant())
            ->get("/admin/requests/detail/{$sr->id}")->assertOk();

        // L'historique reste visible — l'archive doit dire ce qui a manqué —
        // mais il n'y a plus rien à constater.
        $reponse->assertSee('Pièces à fournir plus tard');
        $reponse->assertDontSee('Pièce reçue');
    }

    /* ── Paramétrage (administration) ────────────────── */

    public function test_le_formulaire_expose_le_reglage(): void
    {
        $this->socle();

        // Le formulaire vit dans l'écran d'un niveau, pas sur la page
        // d'accueil qui, elle, ne liste que les grades.
        $this->actingAs($this->superAdmin())
            ->get('/admin/request-types/niveau/licence')
            ->assertOk()
            ->assertSee('name="derogation_possible"', false)
            ->assertSee('Report autorisé');
    }

    public function test_l_administration_ouvre_une_piece_au_report(): void
    {
        $this->socle();

        $this->actingAs($this->superAdmin())->post('/admin/request-types', [
            'label' => 'Certificat de nationalité',
            'category' => 'depot',
            'grade' => 'licence',
            'type_etudiant' => 'both',
            'is_required' => 1,
            'derogation_possible' => 1,
        ]);

        $this->assertDatabaseHas('request_types', [
            'label' => 'Certificat de nationalité',
            'derogation_possible' => true,
        ]);
    }

    public function test_une_piece_facultative_ne_peut_pas_etre_reportable(): void
    {
        $this->socle();

        // Requête forgée : facultative ET reportable. Le report n'a alors
        // aucun sens — il ne doit pas être enregistré.
        $this->actingAs($this->superAdmin())->post('/admin/request-types', [
            'label' => 'Photo d’identité',
            'category' => 'depot',
            'grade' => 'licence',
            'type_etudiant' => 'both',
            'is_required' => 0,
            'derogation_possible' => 1,
        ]);

        $this->assertDatabaseHas('request_types', [
            'label' => 'Photo d’identité',
            'derogation_possible' => false,
        ]);
    }

    public function test_le_report_se_retire_avec_le_caractere_obligatoire(): void
    {
        $this->socle();
        $type = $this->typeDepot('Certificat de nationalité', true);

        $this->actingAs($this->superAdmin())->put("/admin/request-types/{$type->id}", [
            'label' => 'Certificat de nationalité',
            'category' => 'depot',
            'grade' => 'licence',
            'type_etudiant' => 'both',
            'is_required' => 0,
            'derogation_possible' => 1,
        ]);

        $this->assertFalse((bool) $type->fresh()->derogation_possible,
            "Une pièce devenue facultative n'a plus rien à différer.");
    }

    /* ── Archives : elles doivent dire ce qui manquait ─ */

    public function test_l_archive_signale_une_piece_encore_due(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);

        $sr = $this->depot($user, 'validé');
        $sr->update(['validated_at' => now()]);

        Derogation::create([
            'student_request_id' => $sr->id,
            'request_type_id'    => $nationalite->id,
            'statut'             => 'engagee',
            'engagee_at'         => now(),
        ]);

        $this->actingAs($this->agent([
            'acceder-backoffice', 'archives.consulter', 'dossiers.consulter',
        ]))->get('/admin/archives')
            ->assertOk()
            ->assertSee('Certificat de nationalité');
    }

    public function test_l_export_porte_les_pieces_encore_dues(): void
    {
        $this->socle();
        $user = $this->etudiant();
        $nationalite = $this->typeDepot('Certificat de nationalité', true);
        $sr = $this->depot($user, 'soumis');

        Derogation::create([
            'student_request_id' => $sr->id,
            'request_type_id'    => $nationalite->id,
            'statut'             => 'engagee',
            'engagee_at'         => now(),
        ]);

        $rows = StudentRequest::with('student.user', 'files', 'derogations.requestType')
            ->whereKey($sr->id)->get();
        $export = new \App\Exports\DepotsExport($rows);

        $entetes = $export->headings();
        $this->assertContains('pieces_differees', $entetes);
        $this->assertContains('pieces_encore_dues', $entetes);

        $ligne = $export->map($rows->first());
        $this->assertContains('Certificat de nationalité', $ligne,
            "L'export dirait qu'un dossier différé est complet.");
    }
}
