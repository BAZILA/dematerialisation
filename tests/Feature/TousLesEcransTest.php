<?php

namespace Tests\Feature;

use App\Models\Candidature;
use App\Models\Parcours;
use App\Models\Procedure;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Balayage de TOUS les écrans d'administration.
 *
 * Une page qui tombe en 500 ne se voit qu'au moment où quelqu'un
 * l'ouvre — le jour de la rentrée, avec du monde derrière. Ce test
 * ouvre chaque écran et refuse toute erreur serveur.
 *
 * Il ne juge pas le contenu : il garantit qu'aucun écran n'est mort.
 */
class TousLesEcransTest extends TestCase
{
    use RefreshDatabase;

    private ?User $pilote = null;

    private function admin(): User
    {
        return $this->pilote ??= $this->superAdmin();
    }

    /** Le minimum pour que les écrans aient quelque chose à afficher. */
    private function jeuDeDonnees(): array
    {
        $this->socle();

        $parcours = $this->parcours();

        $annee = \App\Models\AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
             'statut' => 'ouverte', 'is_active' => true]
        );

        $type = RequestType::create([
            'label' => 'Acte de naissance', 'category' => 'depot',
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);

        $user = User::create([
            'numero_identifiant' => 'ETU500', 'email' => 'etu500@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        $etudiant = Student::create([
            'user_id' => $user->id, 'nom' => 'ADJO', 'prenom' => 'Kodjo',
            'sexe' => 'M', 'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        $dossier = StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'annee_academique_id' => $annee->id, 'parcours_id' => $parcours->id,
            'status' => 'soumis', 'version' => 1, 'submitted_at' => now(),
        ]);

        $candidature = $this->candidature($parcours);

        return compact('parcours', 'annee', 'type', 'dossier', 'candidature');
    }

    /** @return array<string,string> libellé => URL */
    private function ecransSansParametre(): array
    {
        return [
            'Vue d\'ensemble'        => '/admin/dashboard',
            'Statistiques'           => '/admin/statistiques',
            'Archives'               => '/admin/archives',
            'Journal d\'audit'       => '/admin/audit',
            'Candidatures'           => '/admin/candidatures',
            'Vue par parcours'       => '/admin/candidatures/tableau-bord',
            'Procédures'             => '/admin/procedures',
            'Types de documents'     => '/admin/request-types',
            'Référentiel académique' => '/admin/academique',
            'Années académiques'     => '/admin/annees',
            'Identités'              => '/admin/identites',
            'Utilisateurs'           => '/admin/utilisateurs',
            'Périmètres agents'      => '/admin/perimetres',
            'Rôles & permissions'    => '/admin/roles',
            'Sécurité (2FA)'         => '/admin/securite',
            'Paramètres'             => '/super-admin/parametres',
            'Sauvegardes'            => '/super-admin/sauvegardes',
        ];
    }

    public function test_aucun_ecran_d_administration_ne_tombe(): void
    {
        $this->jeuDeDonnees();
        $admin = $this->admin();

        $casses = [];
        foreach ($this->ecransSansParametre() as $nom => $url) {
            $statut = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($statut !== 200) {
                $casses[] = "{$nom} ({$url}) → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses,
            "Écran(s) en erreur :\n  - " . implode("\n  - ", $casses));
    }

    public function test_l_ecran_des_paiements_s_ouvre_module_allume(): void
    {
        $this->jeuDeDonnees();
        \App\Models\Setting::set('payments.enabled', '1');

        $this->actingAs($this->admin())->get('/admin/paiements')->assertOk();
    }

    public function test_l_ecran_des_paiements_est_introuvable_module_eteint(): void
    {
        $this->jeuDeDonnees();

        // Module éteint : l'écran ne doit pas exister du tout, pas
        // s'afficher vide ni tomber en erreur.
        $this->actingAs($this->admin())->get('/admin/paiements')->assertNotFound();
    }

    public function test_le_cachet_repond_404_tant_qu_aucun_n_est_depose(): void
    {
        $this->jeuDeDonnees();

        // Ce n'est pas un écran : la route sert le fichier du cachet.
        $this->actingAs($this->admin())->get('/super-admin/cachet')->assertNotFound();
    }

    public function test_les_ecrans_de_detail_s_ouvrent(): void
    {
        ['dossier' => $dossier, 'candidature' => $candidature] = $this->jeuDeDonnees();
        $admin = $this->admin();

        $ecrans = [
            'Détail d\'un dépôt'      => "/admin/requests/detail/{$dossier->id}",
            'Détail d\'une candidature' => "/admin/candidatures/{$candidature->id}",
        ];

        $casses = [];
        foreach ($ecrans as $nom => $url) {
            $statut = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($statut !== 200) {
                $casses[] = "{$nom} ({$url}) → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses,
            "Écran(s) de détail en erreur :\n  - " . implode("\n  - ", $casses));
    }

    public function test_chaque_procedure_a_sa_liste(): void
    {
        $this->jeuDeDonnees();
        $admin = $this->admin();

        $casses = [];
        foreach (Procedure::pluck('code') as $code) {
            $statut = $this->actingAs($admin)->get("/admin/requests/{$code}")->getStatusCode();
            if ($statut !== 200) {
                $casses[] = "Liste « {$code} » → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses, implode("\n  - ", $casses));
    }

    public function test_chaque_niveau_a_son_ecran_de_pieces(): void
    {
        $this->jeuDeDonnees();
        $admin = $this->admin();

        $casses = [];
        foreach (array_keys(Parcours::$grades) as $grade) {
            $statut = $this->actingAs($admin)->get("/admin/request-types/niveau/{$grade}")->getStatusCode();
            if ($statut !== 200) {
                $casses[] = "Niveau « {$grade} » → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses, implode("\n  - ", $casses));
    }

    public function test_un_niveau_inconnu_repond_proprement(): void
    {
        $this->jeuDeDonnees();

        // Une URL bricolée doit donner un 404 franc, pas une erreur serveur.
        $this->actingAs($this->admin())
            ->get('/admin/request-types/niveau/inexistant')
            ->assertNotFound();
    }

    /* ── Exports : ils produisent un fichier, pas une page ─ */

    public function test_les_exports_repondent(): void
    {
        $this->jeuDeDonnees();
        $admin = $this->admin();

        $exports = [
            'Dépôts'              => '/admin/requests-export/depot',
            'Modèle bacheliers'   => '/admin/identites/bacheliers/modele',
            'Modèle anciens'      => '/admin/identites/anciens/modele',
            'Export bacheliers'   => '/admin/identites/bacheliers/export',
            'Export anciens'      => '/admin/identites/anciens/export',
            'Audit (xlsx)'        => '/admin/audit/export/xlsx',
            'Statistiques (xlsx)' => '/admin/statistiques/export/xlsx',
            'Candidatures (xlsx)' => '/admin/candidatures/export/xlsx',
        ];

        $casses = [];
        foreach ($exports as $nom => $url) {
            $statut = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($statut >= 500) {
                $casses[] = "{$nom} ({$url}) → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses,
            "Export(s) en erreur :\n  - " . implode("\n  - ", $casses));
    }

    /* ── Espace usager ────────────────────────────────── */

    public function test_les_ecrans_publics_repondent(): void
    {
        $this->jeuDeDonnees();

        $publics = [
            'Connexion'             => '/login',
            'Candidature en ligne'  => '/candidature',
            'Suivi de candidature'  => '/candidature/suivi',
        ];

        $casses = [];
        foreach ($publics as $nom => $url) {
            $statut = $this->get($url)->getStatusCode();
            if ($statut !== 200) {
                $casses[] = "{$nom} ({$url}) → HTTP {$statut}";
            }
        }

        $this->assertSame([], $casses,
            "Écran(s) public(s) en erreur :\n  - " . implode("\n  - ", $casses));
    }

    public function test_l_espace_etudiant_repond(): void
    {
        $this->jeuDeDonnees();

        $etudiant = User::where('numero_identifiant', 'ETU500')->firstOrFail();

        $this->actingAs($etudiant)->get('/student/dashboard')->assertOk();
    }
}
