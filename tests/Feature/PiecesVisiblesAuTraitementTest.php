<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * « On voit que la demande est arrivée mais on ne voit pas les pièces. »
 *
 * L'écran de traitement n'affichait les fichiers qu'au travers de la liste
 * des pièces ATTENDUES. Depuis que chaque niveau a ses propres pièces,
 * cette liste revenait vide faute de niveau transmis : 11 pièces déposées,
 * zéro affichée.
 *
 * Aucun test ne vérifiait que l'agent VOIT les pièces. Les tests
 * vérifiaient qu'elles s'enregistrent, qu'elles se tamponnent, qu'on peut
 * valider — jamais qu'elles apparaissent à qui doit les examiner.
 */
class PiecesVisiblesAuTraitementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:StudentRequest,1:\App\Models\Parcours} */
    private function dossierSoumis(): array
    {
        $this->socle();
        $parcours = $this->parcours();

        $user = User::create([
            'numero_identifiant' => 'ATETTEH', 'email' => 'akua@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        $etudiant = Student::create([
            'user_id' => $user->id, 'nom' => 'TETTEH', 'prenom' => 'Akua',
            'sexe' => 'F', 'date_naissance' => '2002-03-14', 'lieu_naissance' => 'Kara',
        ]);

        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);

        $dossier = StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'parcours_id' => $parcours->id, 'annee_academique_id' => $annee->id,
            'status' => 'soumis', 'version' => 2, 'submitted_at' => now(),
        ]);

        return [$dossier, $parcours];
    }

    private function piece(string $libelle, string $grade): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'depot', 'grade' => $grade,
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);
    }

    private function deposer(StudentRequest $dossier, RequestType $type): RequestFile
    {
        return RequestFile::create([
            'student_request_id' => $dossier->id, 'request_type_id' => $type->id,
            'file_path' => "requests/{$dossier->id}/" . \Illuminate\Support\Str::slug($type->label) . '.pdf',
            'original_filename' => $type->label . '.pdf', 'stored_filename' => \Illuminate\Support\Str::slug($type->label) . '.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 1024,
        ]);
    }

    private function traitant(): User
    {
        return $this->superAdmin();
    }

    /* ── Le bug constaté ────────────────────────────── */

    public function test_l_agent_voit_les_pieces_deposees(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $noms = ['Acte de naissance', 'Certificat de nationalité', "Photo d'identité"];
        foreach ($noms as $nom) {
            $this->deposer($dossier, $this->piece($nom, $parcours->grade));
        }

        $page = $this->actingAs($this->traitant())->get("/admin/requests/detail/{$dossier->id}");
        $page->assertOk();

        foreach ($noms as $nom) {
            $page->assertSee($nom);
        }
    }

    /* ── Ce qui cachait les pièces ─────────────────── */

    public function test_une_piece_desactivee_apres_depot_reste_visible(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $type = $this->piece('Relevé de notes du Bac', $parcours->grade);
        $this->deposer($dossier, $type);

        // L'administration retire la pièce APRÈS le dépôt. Le fichier fourni
        // ne doit pas disparaître pour autant de l'écran de l'agent.
        $type->update(['is_active' => false]);

        $this->actingAs($this->traitant())->get("/admin/requests/detail/{$dossier->id}")
            ->assertOk()
            ->assertSee('Relevé de notes du Bac');
    }

    public function test_une_piece_d_un_autre_niveau_reste_visible(): void
    {
        [$dossier] = $this->dossierSoumis();

        // Pièce rattachée à un autre niveau que celui du dossier : c'est
        // exactement la situation créée par la bascule vers les niveaux.
        $type = $this->piece('Diplôme de Licence', 'master');
        $this->deposer($dossier, $type);

        $this->actingAs($this->traitant())->get("/admin/requests/detail/{$dossier->id}")
            ->assertOk()
            ->assertSee('Diplôme de Licence');
    }

    public function test_un_dossier_sans_parcours_montre_quand_meme_ses_pieces(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $type = $this->piece('Acte de naissance', $parcours->grade);
        $this->deposer($dossier, $type);

        // Sans parcours, aucun niveau : la liste attendue est vide. Les
        // fichiers déposés, eux, doivent rester visibles.
        $dossier->update(['parcours_id' => null]);

        $this->actingAs($this->traitant())->get("/admin/requests/detail/{$dossier->id}")
            ->assertOk()
            ->assertSee('Acte de naissance');
    }

    /* ── Le nombre affiché correspond au nombre déposé ─ */

    public function test_chaque_piece_deposee_apparait_une_seule_fois(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $type = $this->piece('Acte de naissance', $parcours->grade);
        $this->deposer($dossier, $type);

        // Attendue ET déposée : elle ne doit pas apparaître en double.
        $affichees = $dossier->fresh()->typesAAfficher();

        $this->assertCount(1, $affichees->where('id', $type->id));
    }

    public function test_une_piece_attendue_non_deposee_reste_listee(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $this->piece('Certificat de nationalité', $parcours->grade);

        // L'agent doit voir ce qui MANQUE, pas seulement ce qui est là.
        $this->assertSame(['Certificat de nationalité'],
            $dossier->fresh()->typesAAfficher()->pluck('label')->all());
    }

    /* ── La soumission, elle, n'exige que l'attendu ── */

    public function test_une_piece_desactivee_n_est_plus_exigee(): void
    {
        [$dossier, $parcours] = $this->dossierSoumis();

        $type = $this->piece('Ancienne pièce', $parcours->grade);
        $type->update(['is_active' => false]);

        // Visible si elle a été déposée, mais plus jamais exigée.
        $this->assertCount(0, $dossier->fresh()->piecesObligatoiresManquantes());
    }
}
