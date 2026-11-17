<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\Procedure;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Les boutons, pas seulement les écrans.
 *
 * Ouvrir une page prouve peu : le défaut qui a bloqué la désactivation
 * des pièces se produisait à l'enregistrement, sur une page qui
 * s'affichait parfaitement. Ce test exerce chaque geste — créer,
 * modifier, activer, supprimer — et vérifie l'effet en base.
 */
class ToutesLesActionsTest extends TestCase
{
    use RefreshDatabase;

    private ?User $pilote = null;

    private function admin(): User
    {
        return $this->pilote ??= $this->superAdmin();
    }

    /* ── Référentiel académique ─────────────────────── */

    public function test_creer_modifier_supprimer_une_composante(): void
    {
        $this->socle();

        $this->actingAs($this->admin())->post('/admin/academique/facultes', [
            'nom' => 'Faculté des Lettres', 'sigle' => 'FLESH', 'type' => 'faculte',
        ]);
        $faculte = Faculte::where('sigle', 'FLESH')->first();
        $this->assertNotNull($faculte, 'La création de composante ne passe pas.');

        $this->actingAs($this->admin())->put("/admin/academique/facultes/{$faculte->id}", [
            'nom' => 'Faculté des Lettres et Sciences Humaines',
            'sigle' => 'FLESH', 'type' => 'faculte', 'is_active' => 1,
        ]);
        $this->assertSame('Faculté des Lettres et Sciences Humaines', $faculte->fresh()->nom);

        // Désactivation : la case décochée n'est pas envoyée par le navigateur.
        $this->actingAs($this->admin())->put("/admin/academique/facultes/{$faculte->id}", [
            'nom' => 'Faculté des Lettres et Sciences Humaines',
            'sigle' => 'FLESH', 'type' => 'faculte',
        ]);
        $this->assertFalse((bool) $faculte->fresh()->is_active,
            'Décocher « actif » doit désactiver la composante.');

        $this->actingAs($this->admin())->delete("/admin/academique/facultes/{$faculte->id}");
        $this->assertNull(Faculte::find($faculte->id));
    }

    public function test_creer_et_modifier_un_departement(): void
    {
        $this->socle();
        $faculte = Faculte::create([
            'nom' => 'Sciences', 'sigle' => 'FDS', 'type' => 'faculte', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post('/admin/academique/departements', [
            'faculte_id' => $faculte->id, 'nom' => 'Informatique', 'sigle' => 'INFO',
        ]);
        $dep = Departement::where('sigle', 'INFO')->first();
        $this->assertNotNull($dep, 'La création de département ne passe pas.');

        $this->actingAs($this->admin())->put("/admin/academique/departements/{$dep->id}", [
            'faculte_id' => $faculte->id, 'nom' => 'Mathématiques et Informatique',
            'sigle' => 'INFO', 'is_active' => 1,
        ]);
        $this->assertSame('Mathématiques et Informatique', $dep->fresh()->nom);
    }

    public function test_creer_et_modifier_un_parcours(): void
    {
        $this->socle();
        $parcours = $this->parcours();

        $this->actingAs($this->admin())->put("/admin/academique/parcours/{$parcours->id}", [
            'departement_id' => $parcours->departement_id,
            'nom' => 'Génie Logiciel', 'sigle' => 'GL',
            'grade' => 'master', 'is_active' => 1,
        ]);

        $frais = $parcours->fresh();
        $this->assertSame('Génie Logiciel', $frais->nom);
        $this->assertSame('master', $frais->grade);
    }

    /* ── Années académiques ─────────────────────────── */

    public function test_creer_une_annee_et_l_activer(): void
    {
        $this->socle();

        $this->actingAs($this->admin())->post('/admin/annees', [
            'libelle' => '2027-2028', 'statut' => 'en_preparation',
            'date_debut' => '2027-09-01', 'date_fin' => '2028-08-31',
        ]);
        $annee = AnneeAcademique::where('libelle', '2027-2028')->first();
        $this->assertNotNull($annee, "La création d'année ne passe pas.");

        $this->actingAs($this->admin())->post("/admin/annees/{$annee->id}/activer");
        $this->assertTrue((bool) $annee->fresh()->is_active);
    }

    public function test_une_annee_en_double_est_refusee(): void
    {
        $this->socle();
        AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post('/admin/annees', [
            'libelle' => '2026-2027', 'statut' => 'ouverte',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
        ])->assertSessionHasErrors();

        $this->assertSame(1, AnneeAcademique::where('libelle', '2026-2027')->count());
    }

    /* ── Procédures ─────────────────────────────────── */

    public function test_activer_et_desactiver_une_procedure(): void
    {
        $this->socle();
        $proc = Procedure::where('code', 'attestation')->first();
        $this->assertNotNull($proc, 'Procédure « attestation » absente du socle.');

        // Case décochée = champ absent : la procédure doit se désactiver.
        $this->actingAs($this->admin())->put("/admin/procedures/{$proc->id}", [
            'nom' => $proc->nom,
        ]);
        $this->assertFalse((bool) $proc->fresh()->is_active,
            "Décocher « actif » doit désactiver la procédure.");

        $this->actingAs($this->admin())->put("/admin/procedures/{$proc->id}", [
            'nom' => $proc->nom, 'is_active' => 1,
        ]);
        $this->assertTrue((bool) $proc->fresh()->is_active);
    }

    /* ── Utilisateurs ───────────────────────────────── */

    public function test_creer_modifier_desactiver_supprimer_un_agent(): void
    {
        $this->socle();

        $this->actingAs($this->admin())->post('/admin/utilisateurs', [
            'prenom' => 'Ama', 'nom' => 'KOSSI',
            'numero_identifiant' => 'DAAS20', 'email' => 'ama.kossi@test.local',
            'roles' => ['agent_candidature'], 'password' => 'Secret12345',
        ]);
        $agent = User::where('numero_identifiant', 'DAAS20')->first();
        $this->assertNotNull($agent, "La création d'utilisateur ne passe pas.");
        $this->assertTrue($agent->hasRole('agent_candidature'));

        // Modification sans mot de passe : il doit rester inchangé.
        $empreinte = $agent->password;
        $this->actingAs($this->admin())->put("/admin/utilisateurs/{$agent->id}", [
            'prenom' => 'Ama', 'nom' => 'KOSSI-ADJO',
            'email' => 'ama.kossi@test.local',
            'roles' => ['agent_candidature'], 'is_active' => 1,
        ]);
        $agent = $agent->fresh();
        $this->assertSame('KOSSI-ADJO', $agent->nom);
        $this->assertSame($empreinte, $agent->password,
            'Laisser le mot de passe vide ne doit pas le changer.');

        // Désactivation : case absente
        $this->actingAs($this->admin())->put("/admin/utilisateurs/{$agent->id}", [
            'prenom' => 'Ama', 'nom' => 'KOSSI-ADJO',
            'email' => 'ama.kossi@test.local', 'roles' => ['agent_candidature'],
        ]);
        $this->assertFalse((bool) $agent->fresh()->is_active,
            'Décocher « compte actif » doit désactiver le compte.');

        $this->actingAs($this->admin())->delete("/admin/utilisateurs/{$agent->id}");
        $this->assertNull(User::find($agent->id));
    }

    public function test_un_identifiant_en_double_est_refuse(): void
    {
        $this->socle();
        User::create([
            'numero_identifiant' => 'DAAS21', 'nom' => 'A', 'prenom' => 'B',
            'email' => 'a@test.local', 'password' => Hash::make('secret1234'),
            'role' => 'agent_candidature', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post('/admin/utilisateurs', [
            'prenom' => 'C', 'nom' => 'D',
            'numero_identifiant' => 'DAAS21', 'email' => 'c@test.local',
            'roles' => ['agent_candidature'], 'password' => 'Secret12345',
        ])->assertSessionHasErrors();

        $this->assertSame(1, User::where('numero_identifiant', 'DAAS21')->count());
    }

    /* ── Décisions sur un dossier ───────────────────── */

    private function dossierSoumis(): StudentRequest
    {
        $user = User::create([
            'numero_identifiant' => 'ETU900', 'email' => 'etu900@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        $etudiant = Student::create([
            'user_id' => $user->id, 'nom' => 'ADJO', 'prenom' => 'Kodjo',
            'sexe' => 'M', 'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        $annee = AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
             'statut' => 'ouverte', 'is_active' => true]
        );

        return StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'annee_academique_id' => $annee->id, 'status' => 'soumis',
            'version' => 1, 'submitted_at' => now(),
        ]);
    }

    public function test_valider_un_dossier(): void
    {
        $this->socle();
        $dossier = $this->dossierSoumis();

        $this->actingAs($this->admin())
            ->post("/admin/requests/{$dossier->id}/validate", ['admin_comment' => 'Conforme.']);

        $this->assertSame('validé', $dossier->fresh()->status);
        $this->assertNotNull($dossier->fresh()->validated_at);
    }

    public function test_renvoyer_un_dossier_en_correction(): void
    {
        $this->socle();
        $dossier = $this->dossierSoumis();

        $this->actingAs($this->admin())
            ->post("/admin/requests/{$dossier->id}/reject",
                ['rejection_message' => 'La copie de la pièce est illisible.']);

        $this->assertNotSame('soumis', $dossier->fresh()->status);
    }

    public function test_un_renvoi_sans_motif_ne_casse_pas(): void
    {
        $this->socle();
        $dossier = $this->dossierSoumis();

        // Le champ est facultatif : son absence provoquait une erreur 500.
        $reponse = $this->actingAs($this->admin())
            ->post("/admin/requests/{$dossier->id}/reject", []);

        $this->assertLessThan(500, $reponse->getStatusCode());
    }

    public function test_rejeter_definitivement_un_dossier(): void
    {
        $this->socle();
        $dossier = $this->dossierSoumis();

        $this->actingAs($this->admin())->post("/admin/requests/{$dossier->id}/reject-final", [
            'rejection_message' => 'Dossier non recevable en cet etat.',
        ]);

        $this->assertSame('rejeté', $dossier->fresh()->status);
    }

    public function test_rouvrir_un_dossier_valide(): void
    {
        $this->socle();
        $dossier = $this->dossierSoumis();
        $dossier->update(['status' => 'validé', 'validated_at' => now()]);

        $this->actingAs($this->admin())->post("/admin/requests/{$dossier->id}/reopen", [
            'reopen_message' => 'Erreur de saisie constatée sur le parcours.',
        ]);

        $this->assertNotSame('validé', $dossier->fresh()->status);
    }

    /* ── Paramètres ─────────────────────────────────── */

    public function test_enregistrer_les_parametres(): void
    {
        $this->socle();

        $this->actingAs($this->admin())->post('/super-admin/parametres', [
            'settings' => ['payments.enabled' => '1'],
        ]);

        $this->assertTrue((bool) \App\Models\Setting::get('payments.enabled'),
            "L'enregistrement des paramètres ne prend pas.");
    }

    /* ── Ce qui doit rester interdit ────────────────── */

    public function test_un_agent_sans_droit_ne_peut_rien_creer(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice'], 'AGENT900');

        $this->actingAs($agent)->post('/admin/utilisateurs', [
            'prenom' => 'X', 'nom' => 'Y',
            'numero_identifiant' => 'DAAS99', 'email' => 'x@test.local',
            'roles' => ['agent_candidature'], 'password' => 'Secret12345',
        ])->assertForbidden();

        $this->assertNull(User::where('numero_identifiant', 'DAAS99')->first());
    }

    public function test_un_visiteur_ne_touche_a_rien(): void
    {
        $this->socle();
        $parcours = $this->parcours();

        $this->delete("/admin/academique/parcours/{$parcours->id}")
            ->assertRedirect();

        $this->assertNotNull(Parcours::find($parcours->id),
            "Un visiteur non connecté ne doit rien pouvoir supprimer.");
    }
}
