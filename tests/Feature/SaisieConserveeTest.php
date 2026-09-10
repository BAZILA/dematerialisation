<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Une validation refusée ne doit pas emporter la saisie.
 *
 * Le formulaire des utilisateurs vit dans une fenêtre modale : après la
 * redirection d'erreur elle se refermait, l'usager ne voyait même pas
 * pourquoi, et tout ce qu'il avait tapé était perdu.
 */
class SaisieConserveeTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    /** Le même compte pour toute la durée du test. */
    private function pilote(): User
    {
        return $this->admin ??= $this->superAdmin();
    }

    private function saisieRefusee(): array
    {
        return [
            'prenom' => 'Yentougle',
            'nom' => 'MOUTORE',
            'numero_identifiant' => 'DAAS07',
            'email' => 'pas-une-adresse',        // ce qui fera échouer
            'roles' => ['agent_candidature'],
        ];
    }

    public function test_la_saisie_survit_a_un_refus(): void
    {
        $this->socle();

        $this->actingAs($this->pilote())
            ->from('/admin/utilisateurs')
            ->post('/admin/utilisateurs', $this->saisieRefusee())
            ->assertSessionHasErrors();

        $page = $this->actingAs($this->pilote())->get('/admin/utilisateurs')->assertOk();

        $page->assertSee('value="Yentougle"', false);
        $page->assertSee('value="MOUTORE"', false);
        $page->assertSee('value="DAAS07"', false);
    }

    public function test_le_role_coche_reste_coche(): void
    {
        $this->socle();

        $this->actingAs($this->pilote())
            ->from('/admin/utilisateurs')
            ->post('/admin/utilisateurs', $this->saisieRefusee())
            ->assertSessionHasErrors();

        $this->actingAs($this->pilote())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertSee('value="agent_candidature" id="ur_', false);
    }

    public function test_le_motif_du_refus_est_affiche(): void
    {
        $this->socle();

        $this->actingAs($this->pilote())
            ->from('/admin/utilisateurs')
            ->post('/admin/utilisateurs', $this->saisieRefusee())
            ->assertSessionHasErrors('email');

        $this->actingAs($this->pilote())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertSee('Enregistrement refusé');
    }

    public function test_le_mot_de_passe_n_est_jamais_reinjecte(): void
    {
        $this->socle();

        $saisie = $this->saisieRefusee();
        $saisie['password'] = 'motdepasse-secret-123';

        $this->actingAs($this->pilote())
            ->from('/admin/utilisateurs')
            ->post('/admin/utilisateurs', $saisie)
            ->assertSessionHasErrors();

        $this->actingAs($this->pilote())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertDontSee('motdepasse-secret-123');
    }

    public function test_sans_erreur_le_formulaire_reste_vierge(): void
    {
        $this->socle();

        $this->actingAs($this->pilote())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertDontSee('Enregistrement refusé');
    }

    public function test_une_modification_refusee_ne_repasse_pas_en_creation(): void
    {
        $this->socle();

        $cible = User::create([
            'numero_identifiant' => 'DAAS09', 'nom' => 'KODJO', 'prenom' => 'Ama',
            'email' => 'ama@test.local', 'password' => Hash::make('secret1234'),
            'role' => 'agent_candidature', 'is_active' => true,
        ]);

        $this->actingAs($this->pilote())
            ->from('/admin/utilisateurs')
            ->put("/admin/utilisateurs/{$cible->id}", [
                '_cible' => "/admin/utilisateurs/{$cible->id}",
                'prenom' => 'Ama', 'nom' => 'KODJO',
                'email' => 'pas-une-adresse',
                'roles' => ['agent_candidature'],
            ])
            ->assertSessionHasErrors();

        // Le témoin doit revenir dans la page : sans lui, la fenêtre se
        // rouvrirait en création et écraserait la mauvaise ligne.
        $this->actingAs($this->pilote())->get('/admin/utilisateurs')
            ->assertOk()
            ->assertSee('name="_cible"', false)
            ->assertSee("/admin/utilisateurs/{$cible->id}", false);
    }
}
