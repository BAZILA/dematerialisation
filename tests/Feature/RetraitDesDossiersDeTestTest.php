<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Parcours;
use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les etudiants ont essaye l'application pendant la presentation. On veut
 * retirer ce qu'ils ont fait — dossiers, pieces, paiements — SANS toucher
 * a leurs comptes ni a la configuration, pour que chacun retrouve a sa
 * prochaine connexion le bouton « Creer mon dossier ».
 *
 * Le danger d'un script de suppression n'est pas ce qu'il efface : c'est
 * ce qu'il efface EN PLUS. Chaque test ci-dessous pose un temoin qui ne
 * doit pas bouger.
 */
class RetraitDesDossiersDeTestTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = __DIR__
        . '/../../database/migrations/2026_11_03_090000_retirer_les_dossiers_de_test.php';

    /** Rejoue la migration sur la base du test. */
    private function lancerLaMigration(): void
    {
        (require self::MIGRATION)->up();
    }

    /** Un etudiant avec son compte et sa fiche. */
    private function etudiant(string $identifiant, string $nom): Student
    {
        $user = User::create([
            'numero_identifiant' => $identifiant,
            'email'         => strtolower($identifiant) . '@test.local',
            'password'      => Hash::make('secret1234'),
            'role'          => 'etudiant',
            'type_etudiant' => 'ancien',
            'is_active'     => true,
        ]);
        $user->assignRole('etudiant');

        return Student::create([
            'user_id' => $user->id, 'nom' => $nom, 'prenom' => 'Test',
            'sexe' => 'F', 'date_naissance' => '2002-03-14', 'lieu_naissance' => 'Kara',
        ]);
    }

    /** Un dossier complet : pieces sur le disque, etudes, derogation, paiement. */
    private function dossierAvecTout(Student $etudiant, Parcours $parcours, AnneeAcademique $annee): StudentRequest
    {
        $dossier = StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'parcours_id' => $parcours->id, 'annee_academique_id' => $annee->id,
            'status' => 'soumis', 'submitted_at' => now(),
        ]);

        $piece = RequestType::create([
            'label' => 'Acte de naissance', 'category' => 'depot', 'grade' => 'licence',
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);

        $original = "requests/{$dossier->id}/acte.pdf";
        $tamponne = "requests/{$dossier->id}/acte-tamponne.pdf";
        Storage::disk('local')->put($original, 'PDF');
        Storage::disk('local')->put($tamponne, 'PDF tamponne');

        RequestFile::create([
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'file_path' => $original, 'stamped_file_path' => $tamponne, 'is_stamped' => true,
            'original_filename' => 'acte.pdf', 'stored_filename' => 'acte.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 3,
        ]);

        DB::table('etudes_anterieures')->insert([
            'student_request_id' => $dossier->id,
            'annee_debut' => '2024', 'annee_fin' => '2025', 'universite' => 'UK',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('derogations')->insert([
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'statut' => 'engagee', 'engagee_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('paiements')->insert([
            'reference' => 'PAY-' . $dossier->id,
            'payable_type' => StudentRequest::class, 'payable_id' => $dossier->id,
            'montant' => 5000, 'statut' => 'valide',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $dossier;
    }

    /** @test */
    public function le_dossier_part_avec_tout_ce_qui_en_depend(): void
    {
        $this->socle();
        Storage::fake('local');

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);
        $dossier = $this->dossierAvecTout($this->etudiant('ATETTEH', 'TETTEH'), $this->parcours(), $annee);

        $this->lancerLaMigration();

        // Le dossier, puis tout ce qui n'existe qu'a travers lui.
        $this->assertDatabaseCount('student_requests', 0);
        $this->assertDatabaseCount('request_files', 0);
        $this->assertDatabaseCount('etudes_anterieures', 0);
        $this->assertDatabaseCount('derogations', 0);

        // Le paiement ne pointe son dossier que par reference polymorphe :
        // aucune cle etrangere ne l'emporterait en cascade.
        $this->assertDatabaseMissing('paiements', ['payable_id' => $dossier->id]);

        Storage::disk('local')->assertMissing("requests/{$dossier->id}/acte.pdf");
        Storage::disk('local')->assertMissing("requests/{$dossier->id}/acte-tamponne.pdf");
    }

    /** @test */
    public function le_compte_de_l_etudiant_survit_et_revoit_le_bouton_creer_mon_dossier(): void
    {
        $this->socle();
        Storage::fake('local');

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);
        $etudiant = $this->etudiant('ATETTEH', 'TETTEH');
        $this->dossierAvecTout($etudiant, $this->parcours(), $annee);

        $this->lancerLaMigration();

        $this->assertDatabaseHas('users', ['numero_identifiant' => 'ATETTEH']);
        $this->assertDatabaseHas('students', ['id' => $etudiant->id]);

        $compte = User::where('numero_identifiant', 'ATETTEH')->first();
        $this->assertTrue(
            $compte->hasRole('etudiant'),
            'le role doit rester : sans lui, l etudiant perdrait l acces a son espace'
        );

        // Ce que l'etudiant voit vraiment, pas seulement ce que la base
        // contient : l'ecran doit retomber sur l'invitation a deposer.
        // (Le bouton lui-meme n'apparait que si la periode est ouverte ;
        // ce bloc, lui, est le signe qu'aucun dossier ne subsiste.)
        $reponse = $this->actingAs($compte)->get(route('student.dashboard'));
        $reponse->assertOk();
        $reponse->assertSee('Commencer mon dépôt de dossier', false);
        $reponse->assertDontSee('Dossier soumis', false);
    }

    /** @test */
    public function la_configuration_et_le_personnel_ne_bougent_pas(): void
    {
        $this->socle();
        Storage::fake('local');

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);
        $parcours = $this->parcours();
        $this->dossierAvecTout($this->etudiant('ATETTEH', 'TETTEH'), $parcours, $annee);

        $admin  = $this->superAdmin();
        $pieces = RequestType::count();

        // Temoins hors perimetre : ils ne sont references par aucun dossier.
        Storage::disk('local')->put('signatures/agent.png', 'signature');
        Storage::disk('local')->put('sauvegardes/base.sql', 'dump');

        $this->lancerLaMigration();

        $this->assertDatabaseHas('users', ['numero_identifiant' => $admin->numero_identifiant]);
        $this->assertSame($pieces, RequestType::count(), 'les pieces attendues sont de la configuration');
        $this->assertDatabaseHas('parcours', ['id' => $parcours->id]);
        $this->assertDatabaseHas('annees_academiques', ['id' => $annee->id]);
        $this->assertGreaterThan(0, DB::table('settings')->count(), 'les reglages doivent rester');

        Storage::disk('local')->assertExists('signatures/agent.png');
        Storage::disk('local')->assertExists('sauvegardes/base.sql');
    }

    /** @test */
    public function les_candidatures_publiques_restent_par_defaut(): void
    {
        $this->socle();
        Storage::fake('local');

        $candidature = $this->candidature($this->parcours());

        $this->lancerLaMigration();

        $this->assertDatabaseHas('candidatures', ['id' => $candidature->id]);
        $this->assertDatabaseHas('candidature_choix', ['candidature_id' => $candidature->id]);
    }

    /** @test */
    public function relancer_la_migration_une_seconde_fois_ne_casse_rien(): void
    {
        $this->socle();
        Storage::fake('local');

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);
        $etudiant = $this->etudiant('ATETTEH', 'TETTEH');
        $this->dossierAvecTout($etudiant, $this->parcours(), $annee);

        $this->lancerLaMigration();
        $this->lancerLaMigration();

        $this->assertDatabaseCount('student_requests', 0);
        $this->assertDatabaseHas('users', ['numero_identifiant' => 'ATETTEH']);
    }
}
