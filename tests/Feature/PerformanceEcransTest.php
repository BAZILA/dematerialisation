<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Candidature;
use App\Models\Parcours;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Combien de requêtes SQL par écran ?
 *
 * Un écran qui interroge la base une fois par ligne tient très bien
 * avec dix dossiers, et s'effondre à mille. Le défaut ne se voit pas
 * en préparation : il se voit le jour de la rentrée. On plafonne donc
 * le nombre de requêtes, et on remplit assez de lignes pour qu'un
 * appel par ligne creve le plafond.
 *
 * Les seuils sont larges à dessein : ils n'attrapent pas la lenteur,
 * ils attrapent la CROISSANCE — une requête par dossier affiché.
 */
class PerformanceEcransTest extends TestCase
{
    use RefreshDatabase;

    private const DOSSIERS = 40;

    /** Exécute et compte les requêtes SQL d'un appel. */
    private function compter(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    private function remplir(): array
    {
        $this->socle();
        $parcours = $this->parcours();

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);

        RequestType::create([
            'label' => 'Acte de naissance', 'category' => 'depot', 'grade' => 'licence',
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);

        for ($i = 1; $i <= self::DOSSIERS; $i++) {
            $user = User::create([
                'numero_identifiant' => 'PERF' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'email' => "perf{$i}@test.local", 'password' => Hash::make('secret1234'),
                'role' => 'etudiant', 'type_etudiant' => 'nouveau', 'is_active' => true,
            ]);
            $user->assignRole('etudiant');

            $etudiant = Student::create([
                'user_id' => $user->id, 'nom' => 'NOM' . $i, 'prenom' => 'Prenom' . $i,
                'sexe' => $i % 2 ? 'M' : 'F', 'date_naissance' => '2006-01-10',
                'lieu_naissance' => 'Lomé',
            ]);

            StudentRequest::create([
                'student_id' => $etudiant->id, 'category' => 'depot',
                'parcours_id' => $parcours->id, 'annee_academique_id' => $annee->id,
                'status' => 'soumis', 'version' => 1, 'submitted_at' => now(),
            ]);
        }

        return compact('parcours', 'annee');
    }

    /* ── Les listes ─────────────────────────────────── */

    public function test_la_liste_des_depots_ne_grandit_pas_avec_le_nombre_de_dossiers(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/requests/depot'));

        $this->assertLessThan(self::DOSSIERS, $n,
            "La liste fait {$n} requetes pour " . self::DOSSIERS . " dossiers : "
            . "c'est le profil d'une requete par ligne, qui s'effondrera en volume.");
    }

    public function test_le_tableau_de_bord_tient_en_peu_de_requetes(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/dashboard'));

        $this->assertLessThan(60, $n,
            "Le tableau de bord fait {$n} requetes : il agrege, il ne doit pas parcourir.");
    }

    public function test_les_archives_tiennent_en_peu_de_requetes(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/archives'));

        $this->assertLessThan(self::DOSSIERS, $n,
            "Les archives font {$n} requetes pour " . self::DOSSIERS . " dossiers.");
    }

    public function test_les_statistiques_tiennent_en_peu_de_requetes(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/statistiques'));

        $this->assertLessThan(60, $n,
            "Les statistiques font {$n} requetes : elles agregent en SQL.");
    }

    /* ── Les écrans de paramétrage ──────────────────── */

    public function test_l_ecran_d_un_niveau_tient_en_peu_de_requetes(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/request-types/niveau/licence'));

        $this->assertLessThan(60, $n,
            "L'ecran d'un niveau fait {$n} requetes.");
    }

    public function test_la_liste_des_utilisateurs_tient_en_peu_de_requetes(): void
    {
        $this->remplir();
        $admin = $this->superAdmin();

        // 40 comptes créés : une requête par compte serait visible ici.
        $n = $this->compter(fn () => $this->actingAs($admin)->get('/admin/utilisateurs'));

        $this->assertLessThan(self::DOSSIERS, $n,
            "La liste des utilisateurs fait {$n} requetes pour " . self::DOSSIERS . " comptes.");
    }

    /* ── L'espace usager ────────────────────────────── */

    public function test_le_tableau_de_bord_etudiant_tient_en_peu_de_requetes(): void
    {
        $this->remplir();
        $usager = User::where('numero_identifiant', 'PERF0001')->firstOrFail();

        $n = $this->compter(fn () => $this->actingAs($usager)->get('/student/dashboard'));

        $this->assertLessThan(80, $n,
            "L'espace usager fait {$n} requetes pour un seul dossier.");
    }
}
