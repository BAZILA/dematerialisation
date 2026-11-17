<?php

namespace Tests\Feature;

use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Corriger une fiche du référentiel.
 *
 * L'identité civile sert de clé de connexion : une faute de frappe sur
 * le nom ou la date empêche l'usager d'entrer, et rien ne permettait de
 * la corriger — on ne pouvait qu'ajouter ou supprimer.
 *
 * Elle vit aussi à deux endroits dès qu'un compte existe : la fiche du
 * référentiel, et la fiche « student » qui en porte une copie et qui
 * alimente les documents tamponnés. Les laisser diverger produirait un
 * diplôme au mauvais nom.
 */
class EditionDesFichesTest extends TestCase
{
    use RefreshDatabase;

    private function bachelier(array $ecrase = []): Bachelier
    {
        return Bachelier::create(array_merge([
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
            'numero_releve' => 'REL-001', 'annee_bac' => '2026',
            'is_registered' => false,
        ], $ecrase));
    }

    private function ancien(array $ecrase = []): AncienEtudiant
    {
        return AncienEtudiant::create(array_merge([
            'numero_carte' => 'CARTE-001', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'is_registered' => false,
        ], $ecrase));
    }

    private function champsBachelier(array $ecrase = []): array
    {
        return array_merge([
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
            'numero_releve' => 'REL-001',
        ], $ecrase);
    }

    private function champsAncien(array $ecrase = []): array
    {
        return array_merge([
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'numero_carte' => 'CARTE-001',
        ], $ecrase);
    }

    /* ── La correction passe ────────────────────────── */

    public function test_corriger_le_nom_d_un_bachelier(): void
    {
        $this->socle();
        $fiche = $this->bachelier();

        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/bacheliers/{$fiche->id}",
                $this->champsBachelier(['nom' => 'ADJOVI']));

        $this->assertSame('ADJOVI', $fiche->fresh()->nom);
    }

    public function test_corriger_la_carte_d_un_ancien(): void
    {
        $this->socle();
        $fiche = $this->ancien();

        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/anciens/{$fiche->id}",
                $this->champsAncien(['numero_carte' => 'CARTE-999']));

        $this->assertSame('CARTE-999', $fiche->fresh()->numero_carte);
    }

    public function test_enregistrer_sans_rien_changer_ne_bute_pas_sur_l_unicite(): void
    {
        $this->socle();
        $fiche = $this->ancien();

        // L'unicité doit ignorer la fiche modifiée : sinon on ne pourrait
        // plus enregistrer sans changer son propre numéro.
        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/anciens/{$fiche->id}", $this->champsAncien())
            ->assertSessionHasNoErrors();
    }

    /* ── La copie suit ──────────────────────────────── */

    public function test_la_fiche_etudiant_suit_la_correction(): void
    {
        $this->socle();
        $fiche = $this->ancien(['is_registered' => true]);

        $user = User::create([
            'numero_identifiant' => 'ANC001', 'email' => 'anc001@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');
        $etudiant = Student::create([
            'user_id' => $user->id, 'ancien_etudiant_id' => $fiche->id,
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]);

        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/anciens/{$fiche->id}",
                $this->champsAncien(['nom' => 'KOSSI-ADJO']));

        $this->assertSame('KOSSI-ADJO', $fiche->fresh()->nom);
        $this->assertSame('KOSSI-ADJO', $etudiant->fresh()->nom,
            "Sans propagation, le document tamponné porterait l'ancien nom.");
    }

    /* ── Ce qui doit être refusé ────────────────────── */

    public function test_un_bachelier_ne_peut_pas_perdre_ses_deux_numeros(): void
    {
        $this->socle();
        $fiche = $this->bachelier();

        // Sans numéro de suivi ni de relevé, il devient introuvable et ne
        // peut plus se connecter.
        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/bacheliers/{$fiche->id}",
                $this->champsBachelier(['numero_releve' => '', 'numero_suivi' => '']))
            ->assertSessionHasErrors('numero_suivi');

        $this->assertSame('REL-001', $fiche->fresh()->numero_releve);
    }

    public function test_un_numero_deja_pris_est_refuse(): void
    {
        $this->socle();
        $premier = $this->ancien();
        $second  = $this->ancien(['numero_carte' => 'CARTE-002', 'nom' => 'MENSAH']);

        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/anciens/{$second->id}",
                $this->champsAncien(['nom' => 'MENSAH', 'numero_carte' => 'CARTE-001']))
            ->assertSessionHasErrors('numero_carte');

        $this->assertSame('CARTE-002', $second->fresh()->numero_carte);
    }

    public function test_un_agent_sans_droit_ne_corrige_rien(): void
    {
        $this->socle();
        $fiche = $this->ancien();
        $agent = $this->agent(['acceder-backoffice', 'identites.voir'], 'AGENT700');

        $this->actingAs($agent)
            ->put("/admin/identites/anciens/{$fiche->id}",
                $this->champsAncien(['nom' => 'PIRATE']))
            ->assertForbidden();

        $this->assertSame('KOSSI', $fiche->fresh()->nom);
    }

    public function test_la_correction_est_journalisee(): void
    {
        $this->socle();
        $fiche = $this->ancien();

        $this->actingAs($this->superAdmin())
            ->put("/admin/identites/anciens/{$fiche->id}",
                $this->champsAncien(['nom' => 'KOSSI-ADJO']));

        $this->assertDatabaseHas('audit_logs', ['action' => 'identites.modification']);
    }
}
