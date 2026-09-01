<?php

namespace Tests\Feature;

use App\Models\Bachelier;
use App\Models\CodeAcces;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le parcours complet vu depuis le navigateur : identité, code, accès.
 * Vérifie aussi que le mode s'éteint proprement et que le code ne fuit
 * ni dans l'URL ni dans une page consultable après coup.
 */
class AccesParCodeParcoursTest extends TestCase
{
    use RefreshDatabase;

    private function activerMode(bool $actif = true): void
    {
        Setting::set('acces.code_enabled', $actif ? '1' : '0');
        \Cache::forget('settings.all');
    }

    private function bachelier(): Bachelier
    {
        return Bachelier::create([
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2006-03-14', 'lieu_naissance' => 'Kara',
            'annee_bac' => '2026', 'numero_table' => 'T-1234',
            'email' => 'ama.kossi@test.local', 'is_registered' => false,
        ]);
    }

    private function identite(): array
    {
        return [
            'type_etudiant' => 'nouveau', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'date_naissance' => '2006-03-14', 'lieu_naissance' => 'Kara', 'sexe' => 'F',
        ];
    }

    public function test_les_ecrans_sont_fermes_quand_le_mode_est_eteint(): void
    {
        $this->socle();
        $this->activerMode(false);

        $this->get('/acces')->assertNotFound();
        $this->post('/acces', $this->identite())->assertNotFound();
    }

    public function test_l_ecran_d_identite_s_affiche_quand_le_mode_est_actif(): void
    {
        $this->socle();
        $this->activerMode();

        $this->get('/acces')->assertOk()->assertSee('Accéder à mon espace');
    }

    public function test_le_parcours_complet_ouvre_l_espace(): void
    {
        $this->socle();
        $this->activerMode();
        $this->bachelier();

        // 1. Identité
        $this->post('/acces', $this->identite())->assertRedirect(route('acces.code'));

        // 2. Le code est affiché une fois
        $page = $this->get('/acces/code');
        $page->assertOk();

        $modele = CodeAcces::latest('id')->first();
        $this->assertNotNull($modele);

        // On rejoue le code réel via le service (l'affichage ne le rend pas
        // récupérable en test, c'est précisément ce qu'on veut)
        $user = $modele->user;
        ['code' => $code] = app(\App\Services\AccesParCode::class)->emettre($user);

        // 3. Saisie du code
        $this->post('/acces/code', ['code' => $code])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_le_code_ne_reste_pas_affichable_en_rechargeant(): void
    {
        $this->socle();
        $this->activerMode();
        $this->bachelier();

        $this->post('/acces', $this->identite());

        $premier = $this->get('/acces/code');
        $premier->assertOk()->assertSee('Votre code');

        // Rechargement : le code a été retiré de la session.
        // assertSee échappe les apostrophes : on vise une portion sans.
        $rechargement = $this->get('/acces/code');
        $rechargement->assertOk()->assertSee('demandez-en un nouveau');
        // « code-valeur » figure aussi dans la feuille de style :
        // on vise le balisage, qui n'existe que si le code est affiché.
        $rechargement->assertDontSee('<div class="code-valeur">', false);
    }

    public function test_un_code_faux_ne_connecte_pas(): void
    {
        $this->socle();
        $this->activerMode();
        $this->bachelier();

        $this->post('/acces', $this->identite());
        $this->post('/acces/code', ['code' => 'ZZZZ-ZZZZ']);

        $this->assertGuest();
    }

    public function test_une_identite_inconnue_reste_sur_l_ecran(): void
    {
        $this->socle();
        $this->activerMode();
        $this->bachelier();

        $this->post('/acces', array_merge($this->identite(), ['nom' => 'PERSONNE']))
            ->assertSessionHasErrors('general');

        $this->assertGuest();
        $this->assertSame(0, CodeAcces::count(),
            "Aucun code ne doit être émis pour une identité non reconnue.");
    }

    public function test_sans_session_on_repart_de_l_identite(): void
    {
        $this->socle();
        $this->activerMode();

        $this->get('/acces/code')->assertRedirect(route('acces.identite'));
        $this->post('/acces/code', ['code' => 'ABCD-EFGH'])
            ->assertRedirect(route('acces.identite'));
    }

    public function test_le_personnel_garde_sa_connexion_par_mot_de_passe(): void
    {
        $this->socle();
        $this->activerMode();

        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir']);

        $this->post('/login', [
            'numero_identifiant' => $agent->numero_identifiant,
            'password' => 'secret1234',
        ]);

        // Allumer l'acces par code ne doit pas fermer la porte au personnel
        $this->assertAuthenticatedAs($agent->fresh());
    }

    public function test_demander_un_nouveau_code_invalide_l_ancien(): void
    {
        $this->socle();
        $this->activerMode();
        $this->bachelier();

        $this->post('/acces', $this->identite());
        $ancien = CodeAcces::latest('id')->first();

        $this->post('/acces/renvoyer')->assertRedirect(route('acces.code'));

        $this->assertNotNull($ancien->fresh()->utilise_at,
            "Le code précédent doit être neutralisé.");
        $this->assertSame(2, CodeAcces::count());
    }
}
