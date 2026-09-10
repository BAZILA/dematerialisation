<?php

namespace Tests\Feature;

use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccesParCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un compte se crée à la première connexion par code — sans courriel.
 *
 * Les 1165 anciens étudiants ont été importés sans adresse. La colonne
 * « users.email » étant NOT NULL, la création tombait sur « Column
 * 'email' cannot be null » et l'usager recevait une page 500 en pleine
 * connexion, sans comprendre ce qui lui arrivait.
 *
 * Le courriel est désormais facultatif : l'identification se fait par
 * le numéro d'identifiant, jamais par l'adresse.
 */
class AccesSansCourrielTest extends TestCase
{
    use RefreshDatabase;

    private function moduleAllume(): void
    {
        $this->socle();
        Setting::set('acces.code_enabled', '1');
    }

    private function service(): AccesParCode
    {
        return app(AccesParCode::class);
    }

    /* ── Le cas qui plantait ────────────────────────── */

    public function test_un_ancien_sans_courriel_obtient_son_compte(): void
    {
        $this->moduleAllume();

        AncienEtudiant::create([
            'numero_carte' => 'CARTE0000042', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'email' => null, 'is_registered' => false,
        ]);

        $resultat = $this->service()->identifier([
            'type' => 'ancien', 'numero_carte' => 'CARTE0000042',
            'nom' => 'KOSSI', 'prenom' => 'Ama',
            'date_naissance' => '2001-07-02', 'sexe' => 'F',
        ]);

        $this->assertTrue($resultat['ok'] ?? false,
            "L'identification doit aboutir : c'est ici que la page 500 se produisait.");

        $compte = User::whereHas('student', fn ($q) => $q->where('nom', 'KOSSI'))->first();
        $this->assertNotNull($compte, 'Aucun compte créé.');
        $this->assertNull($compte->email, "Un compte sans adresse en garde une nulle.");
        $this->assertTrue($compte->hasRole('etudiant'));
    }

    public function test_un_bachelier_sans_courriel_obtient_son_compte(): void
    {
        $this->moduleAllume();

        Bachelier::create([
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
            'annee_bac' => '2026', 'email' => null, 'is_registered' => false,
        ]);

        $resultat = $this->service()->identifier([
            'type' => 'nouveau',
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'lieu_naissance' => 'Lomé',
            'date_naissance' => '2006-01-10', 'sexe' => 'M',
        ]);

        $this->assertTrue($resultat['ok'] ?? false);
        $this->assertSame(1, User::where('role', 'etudiant')->count());
    }

    public function test_deux_comptes_sans_courriel_cohabitent(): void
    {
        $this->moduleAllume();

        foreach ([['KOSSI', 'Ama', 'F', '2001-07-02', 'C1'],
                  ['ADJO', 'Kodjo', 'M', '2002-03-04', 'C2']] as [$n, $p, $s, $d, $c]) {
            AncienEtudiant::create([
                'numero_carte' => $c, 'nom' => $n, 'prenom' => $p, 'sexe' => $s,
                'date_naissance' => $d, 'lieu_naissance' => 'Kara',
                'email' => null, 'is_registered' => false,
            ]);

            $this->service()->identifier([
                'type' => 'ancien', 'numero_carte' => $c,
                'nom' => $n, 'prenom' => $p, 'date_naissance' => $d, 'sexe' => $s,
            ]);
        }

        // L'index d'unicité sur « email » ne doit pas empêcher deux
        // comptes d'être tous les deux sans adresse.
        $this->assertSame(2, User::where('role', 'etudiant')->count(),
            "Deux adresses nulles ne se gênent pas dans un index unique.");
    }

    /* ── Le courriel est repris quand il existe ─────── */

    public function test_le_courriel_du_referentiel_est_repris(): void
    {
        $this->moduleAllume();

        AncienEtudiant::create([
            'numero_carte' => 'CARTE0000043', 'nom' => 'MENSAH', 'prenom' => 'Kofi',
            'sexe' => 'M', 'date_naissance' => '2000-05-05', 'lieu_naissance' => 'Lomé',
            'email' => 'kofi.mensah@test.local', 'is_registered' => false,
        ]);

        $this->service()->identifier([
            'type' => 'ancien', 'numero_carte' => 'CARTE0000043',
            'nom' => 'MENSAH', 'prenom' => 'Kofi',
            'date_naissance' => '2000-05-05', 'sexe' => 'M',
        ]);

        $compte = User::whereHas('student', fn ($q) => $q->where('nom', 'MENSAH'))->first();
        $this->assertSame('kofi.mensah@test.local', $compte?->email);
    }

    /* ── L'écran ne doit pas cracher non plus ───────── */

    public function test_l_ecran_d_identite_ne_tombe_pas(): void
    {
        $this->moduleAllume();

        AncienEtudiant::create([
            'numero_carte' => 'CARTE0000044', 'nom' => 'TETTEH', 'prenom' => 'Akua',
            'sexe' => 'F', 'date_naissance' => '1999-11-11', 'lieu_naissance' => 'Kara',
            'email' => null, 'is_registered' => false,
        ]);

        $reponse = $this->post('/acces', [
            'type' => 'ancien', 'numero_carte' => 'CARTE0000044',
            'nom' => 'TETTEH', 'prenom' => 'Akua',
            'date_naissance' => '1999-11-11', 'sexe' => 'F',
        ]);

        $this->assertLessThan(500, $reponse->getStatusCode(),
            "Aucune page 500 ne doit plus apparaître pendant la connexion.");
    }

    public function test_une_identite_inconnue_est_refusee_sans_creer_de_compte(): void
    {
        $this->moduleAllume();

        $resultat = $this->service()->identifier([
            'type' => 'ancien', 'numero_carte' => 'INEXISTANT',
            'nom' => 'PERSONNE', 'prenom' => 'Nul',
            'date_naissance' => '1990-01-01', 'sexe' => 'M',
        ]);

        $this->assertFalse($resultat['ok'] ?? true);
        $this->assertSame(0, User::where('role', 'etudiant')->count(),
            "Une identité non reconnue ne doit créer aucun compte.");
    }
}
