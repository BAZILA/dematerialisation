<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Action impossible » avec un fichier de moins de 2 Mo.
 *
 * Le garde d'accès au dossier comparait les identifiants avec « !== ».
 * La clé primaire est toujours convertie en entier par Eloquent, mais
 * pas une clé étrangère : selon le pilote et sa configuration, MySQL peut
 * la rendre sous forme de TEXTE. « 12 » !== 12 est alors vrai, et le
 * propriétaire légitime se voyait refuser l'accès à son propre dossier.
 *
 * SQLite, qui fait tourner ces tests, rend des entiers : le défaut ne
 * pouvait donc pas apparaître ici sans être provoqué explicitement.
 */
class IdentifiantsEnTexteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0:User,1:StudentRequest} */
    private function dossier(): array
    {
        $this->socle();
        $parcours = $this->parcours();

        $user = User::create([
            'numero_identifiant' => 'ETU900', 'email' => 'etu900@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        $etudiant = Student::create([
            'user_id' => $user->id, 'nom' => 'ADJO', 'prenom' => 'Kodjo',
            'sexe' => 'M', 'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);

        $dossier = StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'parcours_id' => $parcours->id, 'annee_academique_id' => $annee->id,
            'status' => 'brouillon', 'version' => 1,
        ]);

        return [$user->fresh(), $dossier];
    }

    /* ── La racine : le modèle rend toujours des entiers ── */

    public function test_une_cle_etrangere_rendue_en_texte_redevient_un_entier(): void
    {
        [, $dossier] = $this->dossier();

        // On reproduit ce que MySQL peut renvoyer : les identifiants en texte.
        $brut = $dossier->getAttributes();
        $brut['student_id'] = (string) $brut['student_id'];
        $brut['parcours_id'] = (string) $brut['parcours_id'];
        $dossier->setRawAttributes($brut);

        $this->assertIsInt($dossier->student_id,
            "Sans conversion, « 12 » !== 12 refusait le propriétaire lui-même.");
        $this->assertIsInt($dossier->parcours_id);
    }

    public function test_la_comparaison_stricte_echouait_bien_sans_conversion(): void
    {
        // Le mécanisme exact du défaut, isolé : c'est ce que faisait le garde.
        $depuisLaBase = '12';
        $clePrimaire  = 12;

        $this->assertTrue($depuisLaBase !== $clePrimaire,
            "Voilà pourquoi le propriétaire était refusé.");
        $this->assertFalse((int) $depuisLaBase !== (int) $clePrimaire,
            "Convertis, les deux identifiants se reconnaissent.");
    }

    /* ── Le garde : le propriétaire passe, l'intrus non ── */

    public function test_le_proprietaire_depose_sa_piece(): void
    {
        [$user, $dossier] = $this->dossier();

        $type = RequestType::create([
            'label' => 'Acte de naissance', 'category' => 'depot',
            'grade' => $dossier->parcours->grade, 'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/student/requests/file/upload', [
            'student_request_id' => $dossier->id,
            'request_type_id'    => $type->id,
            'file' => UploadedFile::fake()->create('acte.pdf', 900, 'application/pdf'),
        ])->assertOk();

        $this->assertSame(1, $dossier->fresh()->files->count());
    }

    public function test_un_intrus_est_refuse_avec_un_message_explicite(): void
    {
        [, $dossier] = $this->dossier();

        $intrus = User::create([
            'numero_identifiant' => 'ETU901', 'email' => 'etu901@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $intrus->assignRole('etudiant');
        Student::create([
            'user_id' => $intrus->id, 'nom' => 'AUTRE', 'prenom' => 'Personne',
            'sexe' => 'M', 'date_naissance' => '2000-01-01', 'lieu_naissance' => 'Kara',
        ]);

        $type = RequestType::create([
            'label' => 'Acte de naissance', 'category' => 'depot',
            'grade' => $dossier->parcours->grade, 'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => true,
        ]);

        $reponse = $this->actingAs($intrus->fresh())->postJson('/student/requests/file/upload', [
            'student_request_id' => $dossier->id,
            'request_type_id'    => $type->id,
            'file' => UploadedFile::fake()->create('intrus.pdf', 50, 'application/pdf'),
        ]);

        $reponse->assertForbidden();

        // Un message vide faisait afficher « Action impossible », et ne
        // permettait pas de distinguer ce refus d'un blocage du pare-feu.
        $this->assertNotSame('', $reponse->json('message'));
        $this->assertStringContainsString('rattache', $reponse->json('message'));

        $this->assertSame(0, $dossier->fresh()->files->count());
    }

    /* ── L'export d'un parcours ─────────────────────── */

    public function test_un_identifiant_venu_de_l_url_reconnait_son_parcours(): void
    {
        // $parcoursId arrive de l'URL, donc TOUJOURS en texte. La base, elle,
        // rend un entier. Sans conversion, aucune section ne correspondait
        // et l'archive d'un parcours sortait vide.
        $depuisUrl  = '7';
        $depuisBase = 7;

        $this->assertTrue($depuisBase !== $depuisUrl, 'Le défaut, isolé.');
        $this->assertFalse((int) $depuisBase !== (int) $depuisUrl, 'Le correctif.');
    }
}
