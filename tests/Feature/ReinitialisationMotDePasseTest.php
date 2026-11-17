<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Le filet de secours.
 *
 * Un compte créé par la connexion par code reçoit un mot de passe
 * aléatoire que personne ne connaît : l'accès passe uniquement par
 * code. Or l'application n'offre aucun « mot de passe oublié ». Le jour
 * où ce module est éteint, ces comptes n'ont plus aucune porte — et
 * leurs dossiers sont dedans.
 *
 * L'administration peut donc remettre un mot de passe provisoire,
 * affiché une seule fois et jamais enregistré en clair.
 */
class ReinitialisationMotDePasseTest extends TestCase
{
    use RefreshDatabase;

    private function usager(string $identifiant = 'ETU700'): User
    {
        $user = User::create([
            'numero_identifiant' => $identifiant, 'email' => null,
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'etudiant', 'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        return $user->fresh();
    }

    /* ── Le geste ───────────────────────────────────── */

    public function test_le_mot_de_passe_change_vraiment(): void
    {
        $this->socle();
        $usager = $this->usager();
        $avant = $usager->password;

        $this->actingAs($this->superAdmin())
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe");

        $this->assertNotSame($avant, $usager->fresh()->password,
            "L'empreinte doit changer, sinon rien n'a été réinitialisé.");
    }

    public function test_le_mot_de_passe_provisoire_est_affiche_une_fois(): void
    {
        $this->socle();
        $usager = $this->usager();

        $reponse = $this->actingAs($this->superAdmin())
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe");

        $reponse->assertSessionHas('mot_de_passe_provisoire');
        $donnees = session('mot_de_passe_provisoire');

        $this->assertSame('ETU700', $donnees['identifiant']);
        $this->assertGreaterThanOrEqual(12, strlen($donnees['valeur']));
    }

    public function test_le_nouveau_mot_de_passe_permet_de_se_connecter(): void
    {
        $this->socle();
        $usager = $this->usager();

        $this->actingAs($this->superAdmin())
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe");

        $provisoire = session('mot_de_passe_provisoire')['valeur'];

        // C'est tout l'objet du filet : le compte redevient accessible.
        $this->assertTrue(Hash::check($provisoire, $usager->fresh()->password),
            "Le mot de passe affiché doit être celui qui ouvre le compte.");
    }

    public function test_le_mot_de_passe_n_est_jamais_journalise_en_clair(): void
    {
        $this->socle();
        $usager = $this->usager();

        $this->actingAs($this->superAdmin())
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe");

        $provisoire = session('mot_de_passe_provisoire')['valeur'];

        $traces = \App\Models\AuditLog::where('action', 'utilisateur.mot_de_passe_reinitialise')->get();
        $this->assertCount(1, $traces, "Le geste doit laisser une trace.");

        foreach ($traces as $trace) {
            $this->assertStringNotContainsString($provisoire, json_encode($trace->toArray()),
                "Le mot de passe ne doit apparaître nulle part en base.");
        }
    }

    /* ── Le filet doit etre ATTEIGNABLE ─────────────── */

    public function test_le_compte_d_un_usager_n_apparait_pas_dans_l_ecran_utilisateurs(): void
    {
        $this->socle();
        $this->usager('ETU701');

        // L'ecran Utilisateurs ne liste que le personnel. C'est voulu,
        // mais cela veut dire que le bouton de reinitialisation ne peut
        // pas y servir aux usagers : il doit vivre ailleurs.
        $this->actingAs($this->superAdmin())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertDontSee('ETU701');
    }

    public function test_le_bouton_est_offert_depuis_l_ecran_des_identites(): void
    {
        $this->socle();

        $fiche = \App\Models\AncienEtudiant::create([
            'numero_carte' => 'CARTE-500', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'is_registered' => true,
        ]);

        $usager = $this->usager('ETU702');
        \App\Models\Student::create([
            'user_id' => $usager->id, 'ancien_etudiant_id' => $fiche->id,
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]);

        // C'est le seul ecran ou le compte d'un usager est visible :
        // le filet doit y etre, sinon il ne sert a personne.
        $this->actingAs($this->superAdmin())->get('/admin/identites')
            ->assertOk()
            ->assertSee(route('admin.utilisateurs.mot-de-passe', $usager), false);
    }

    public function test_reinitialiser_depuis_les_identites_affiche_le_mot_de_passe(): void
    {
        $this->socle();
        $usager = $this->usager('ETU703');

        $reponse = $this->actingAs($this->superAdmin())
            ->from('/admin/identites')
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe");

        // Le retour se fait sur l'ecran d'origine : le mot de passe doit
        // s'y afficher, pas seulement sur l'ecran Utilisateurs.
        $reponse->assertRedirect('/admin/identites');
        $reponse->assertSessionHas('mot_de_passe_provisoire');
    }

    /* ── Ce qui doit être refusé ────────────────────── */

    public function test_un_agent_sans_droit_ne_reinitialise_rien(): void
    {
        $this->socle();
        $usager = $this->usager();
        $avant = $usager->password;

        $agent = $this->agent(['acceder-backoffice', 'utilisateurs.voir'], 'AGENT800');

        $this->actingAs($agent)
            ->post("/admin/utilisateurs/{$usager->id}/mot-de-passe")
            ->assertForbidden();

        $this->assertSame($avant, $usager->fresh()->password);
    }

    public function test_un_visiteur_ne_reinitialise_rien(): void
    {
        $this->socle();
        $usager = $this->usager();
        $avant = $usager->password;

        $this->post("/admin/utilisateurs/{$usager->id}/mot-de-passe")->assertRedirect();

        $this->assertSame($avant, $usager->fresh()->password);
    }

    public function test_un_super_admin_n_est_pas_reinitialisable_par_un_autre_role(): void
    {
        $this->socle();
        $cible = $this->superAdmin();
        $avant = $cible->password;

        // Prendre la main sur un super-administrateur serait une prise de
        // contrôle de toute la plateforme.
        $gestionnaire = $this->agent(
            ['acceder-backoffice', 'utilisateurs.voir', 'utilisateurs.gerer'], 'AGENT801'
        );

        $this->actingAs($gestionnaire)
            ->post("/admin/utilisateurs/{$cible->id}/mot-de-passe")
            ->assertSessionHasErrors();

        $this->assertSame($avant, $cible->fresh()->password);
    }
}
