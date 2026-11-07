<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\RequestFile;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use App\Services\FicheVersoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Retours du premier dépôt définitif réel, avant la présentation aux étudiants.
 */
class RetoursPresentationTest extends TestCase
{
    use RefreshDatabase;

    /* ── Le verso : la DERNIÈRE page, pas la deuxième ── */

    /** Fabrique un PDF de N pages et rend le chemin. */
    private function pdf(int $pages): string
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, "Page {$i}");
        }
        $chemin = tempnam(sys_get_temp_dir(), 'fiche') . '.pdf';
        $pdf->Output($chemin, 'F');

        return $chemin;
    }

    /** Texte extrait de chaque page d'un PDF, grâce à la date posée au verso. */
    private function pagesPortantLaDate(string $chemin): array
    {
        $contenu = file_get_contents($chemin);
        $date = now()->format('d/m/Y');

        // Les pages sont des objets distincts : on cherche lesquels portent la date.
        $parties = preg_split('/\/Type\s*\/Page[^s]/', $contenu);
        $pages = [];
        foreach (array_slice($parties, 1) as $i => $morceau) {
            $pages[$i + 1] = $morceau;
        }

        return $pages;
    }

    public function test_une_fiche_de_trois_pages_est_remplie_sur_la_troisieme(): void
    {
        $this->socle();
        $source = $this->pdf(3);
        $sortie = tempnam(sys_get_temp_dir(), 'verso') . '.pdf';

        $ok = (new FicheVersoService())->fillVerso($source, $sortie, [], null, null, 'Adolphe BAZILA');

        $this->assertTrue($ok, 'Le remplissage a échoué.');

        // Le document garde ses trois pages : aucune n'est perdue ni ajoutée.
        $fpdi = new \setasign\Fpdi\Tcpdf\Fpdi();
        $this->assertSame(3, $fpdi->setSourceFile($sortie));

        @unlink($source); @unlink($sortie);
    }

    public function test_la_page_remplie_est_la_derniere_quelle_que_soit_la_longueur(): void
    {
        $this->socle();

        foreach ([1, 2, 3, 4] as $nbPages) {
            $source = $this->pdf($nbPages);
            $sortie = tempnam(sys_get_temp_dir(), 'verso') . '.pdf';

            $service = new class extends FicheVersoService {
                public array $pagesRemplies = [];
            };

            $this->assertTrue($service->fillVerso($source, $sortie, [], null, null, 'Agent'),
                "Échec sur une fiche de {$nbPages} page(s).");

            $fpdi = new \setasign\Fpdi\Tcpdf\Fpdi();
            $this->assertSame($nbPages, $fpdi->setSourceFile($sortie),
                "Une fiche de {$nbPages} page(s) doit en garder autant.");

            @unlink($source); @unlink($sortie);
        }
    }

    public function test_le_service_ne_vise_plus_la_page_deux(): void
    {
        // Le verso était codé en dur à la page 2. Une fiche de trois pages
        // recevait alors son remplissage au milieu, sur une page sans case.
        $code = file_get_contents(app_path('Services/FicheVersoService.php'));

        $this->assertStringNotContainsString('VERSO_PAGE = 2', $code);
        $this->assertStringContainsString('$versoPage = $pageCount', $code);
    }

    /* ── Plus de « Validé par » à côté du cachet ────── */

    public function test_le_cachet_de_premiere_page_n_est_plus_accompagne_du_nom(): void
    {
        $this->socle();
        $tmp = sys_get_temp_dir();

        // Une image de cachet : sans elle, aucun cachet n'est posé et le
        // contrôle ne prouverait rien.
        $img = imagecreatetruecolor(120, 120);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 60, 160));
        imagepng($img, "{$tmp}/cachet_essai.png");

        $source = $this->pdf(2);
        $sortie = "{$tmp}/fiche_sans_mention.pdf";

        $this->assertTrue((new FicheVersoService())
            ->fillVerso($source, $sortie, [], "{$tmp}/cachet_essai.png", null, 'Adolphe BAZILA'));

        $texte = $this->texteDuPdf($sortie);

        /* Contrôle positif : si l'extraction ne voyait aucun texte, l'absence
           de la mention ne prouverait rien et ce test passerait à vide. Le
           nom du vérificateur, posé au verso, doit donc être retrouvé. */
        $this->assertStringContainsString('Adolphe BAZILA', $texte,
            "L'extraction ne lit plus le texte du PDF : le contrôle suivant serait vide de sens.");

        /* Posé à une position aléatoire, « Validé par : {nom} » recouvrait le
           contenu de la fiche. Le nom du vérificateur figure déjà au verso. */
        $this->assertStringNotContainsString('Validé par', $texte);

        @unlink($source); @unlink($sortie); @unlink("{$tmp}/cachet_essai.png");
    }

    public function test_les_autres_documents_tamponnes_n_ont_plus_la_mention(): void
    {
        $code = file_get_contents(app_path('Services/StampService.php'));

        // Le cachet IMAGE, utilisé en production, ne porte plus la mention.
        $debut = strpos($code, 'private function drawCachetImage');
        $fin   = strpos($code, 'public static function', $debut) ?: strlen($code);
        $corps = substr($code, $debut, $fin - $debut);

        $this->assertStringNotContainsString("'Validé par : '", $corps);
    }

    /** Texte brut d'un PDF, flux décompressés. */
    private function texteDuPdf(string $chemin): string
    {
        $brut = file_get_contents($chemin);
        $texte = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $brut, $m)) {
            foreach ($m[1] as $flux) {
                $dec = @gzuncompress($flux);
                $texte .= $dec !== false ? $dec : $flux;
            }
        }

        return $texte;
    }

    /* ── Le décalage du bas de la fiche ─────────────── */

    private function decalage(FicheVersoService $service, float $y): float
    {
        $methode = new \ReflectionMethod($service, 'bas');
        $methode->setAccessible(true);

        return $methode->invoke($service, $y);
    }

    public function test_le_bas_de_la_fiche_remonte_par_defaut(): void
    {
        $this->socle();

        // Sur la fiche réellement téléchargée, le bloc de signature est plus
        // haut de 4,5 mm que sur le gabarit de calage.
        $this->assertEqualsWithDelta(224.0, $this->decalage(new FicheVersoService(), 228.5), 0.01);
    }

    public function test_le_decalage_se_regle_dans_les_parametres(): void
    {
        $this->socle();
        Setting::set('fiche.decalage_bas_mm', '-2');

        $this->assertEqualsWithDelta(226.5, $this->decalage(new FicheVersoService(), 228.5), 0.01);
    }

    public function test_une_faute_de_frappe_ne_sort_pas_le_texte_de_la_page(): void
    {
        $this->socle();
        Setting::set('fiche.decalage_bas_mm', '-450');   // virgule oubliée

        // Borné à 30 mm : le texte reste sur la page.
        $this->assertEqualsWithDelta(198.5, $this->decalage(new FicheVersoService(), 228.5), 0.01);
    }

    public function test_une_valeur_illisible_retombe_sur_le_defaut(): void
    {
        $this->socle();
        Setting::set('fiche.decalage_bas_mm', 'abc');

        $this->assertEqualsWithDelta(224.0, $this->decalage(new FicheVersoService(), 228.5), 0.01);
    }

    public function test_le_reglage_apparait_dans_les_parametres(): void
    {
        $this->socle();

        $this->actingAs($this->superAdmin())->get('/super-admin/parametres')
            ->assertOk()
            ->assertSee('settings[fiche.decalage_bas_mm]', false);
    }

    /* ── Les noms de fichiers abîmés ────────────────── */

    public function test_un_nom_encode_deux_fois_est_repare(): void
    {
        $abime = "Universit\u{00C3}\u{00A9} de Kara-fiche-inscription-134757-2.pdf";

        $this->assertSame('Université de Kara-fiche-inscription-134757-2.pdf',
            RequestFile::reparerEncodage($abime));
    }

    public function test_un_nom_sain_n_est_jamais_modifie(): void
    {
        foreach (['Acte de naissance.pdf', 'Relevé de notes.pdf', 'Photo d’identité.jpg', 'scan_001.png'] as $nom) {
            $this->assertSame($nom, RequestFile::reparerEncodage($nom),
                "« {$nom} » ne devait pas être touché.");
        }
    }

    public function test_reparer_deux_fois_ne_change_rien(): void
    {
        $abime = "Universit\u{00C3}\u{00A9} de Kara.pdf";
        $une = RequestFile::reparerEncodage($abime);

        $this->assertSame($une, RequestFile::reparerEncodage($une));
    }

    public function test_un_fichier_deja_en_base_s_affiche_repare(): void
    {
        [$dossier] = $this->dossierSoumis();
        $type = \App\Models\RequestType::create([
            'label' => 'Fiche', 'category' => 'depot', 'grade' => 'licence',
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);

        // Écrit directement en base, comme les fichiers déjà déposés avant ce correctif.
        $id = \DB::table('request_files')->insertGetId([
            'student_request_id' => $dossier->id, 'request_type_id' => $type->id,
            'original_filename' => "Universit\u{00C3}\u{00A9} de Kara.pdf",
            'stored_filename' => 'x.pdf', 'file_path' => 'requests/x.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('Université de Kara.pdf', RequestFile::find($id)->original_filename);
    }

    /* ── Le renvoi en correction sans message ───────── */

    /** @return array{0:StudentRequest} */
    private function dossierSoumis(): array
    {
        $this->socle();
        $parcours = $this->parcours();

        $user = User::create([
            'numero_identifiant' => 'ETU950', 'email' => 'etu950@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        $etudiant = Student::create([
            'user_id' => $user->id, 'nom' => 'TETTEH', 'prenom' => 'Akua',
            'sexe' => 'F', 'date_naissance' => '2002-03-14', 'lieu_naissance' => 'Kara',
        ]);

        $annee = AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
             'statut' => 'ouverte', 'is_active' => true]
        );

        return [StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'parcours_id' => $parcours->id, 'annee_academique_id' => $annee->id,
            'status' => 'soumis', 'version' => 1, 'submitted_at' => now(),
        ])];
    }

    public function test_renvoyer_en_correction_sans_message(): void
    {
        [$dossier] = $this->dossierSoumis();

        $this->actingAs($this->superAdmin())
            ->post("/admin/requests/{$dossier->id}/reject", [])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_correction', $dossier->fresh()->status,
            "Le renvoi doit passer sans exiger de message.");
    }

    public function test_un_message_court_suffit(): void
    {
        [$dossier] = $this->dossierSoumis();

        $this->actingAs($this->superAdmin())
            ->post("/admin/requests/{$dossier->id}/reject", ['rejection_message' => 'Illisible'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Illisible', $dossier->fresh()->rejection_message);
    }

    public function test_sans_message_l_etudiant_recoit_une_indication(): void
    {
        [$dossier] = $this->dossierSoumis();

        $this->actingAs($this->superAdmin())->post("/admin/requests/{$dossier->id}/reject", []);

        // Il ne doit jamais rester sans savoir quoi faire.
        $this->assertStringContainsString('commentaire', $dossier->fresh()->rejection_message);
    }

    public function test_le_rejet_definitif_exige_toujours_un_vrai_motif(): void
    {
        [$dossier] = $this->dossierSoumis();

        // Seul le renvoi en correction est assoupli : un rejet définitif
        // engage l'étudiant, il doit rester motivé.
        $this->actingAs($this->superAdmin())
            ->post("/admin/requests/{$dossier->id}/reject-final", ['rejection_message' => 'Non'])
            ->assertSessionHasErrors('rejection_message');

        $this->assertSame('soumis', $dossier->fresh()->status);
    }

    /* ── Mobile ─────────────────────────────────────── */

    public function test_la_classe_min_w_0_est_enfin_definie(): void
    {
        // Utilisée sans jamais être définie : c'est une classe Tailwind,
        // Bootstrap 5 l'ignore. Sans elle, un nom long débordait sur mobile.
        $vue = file_get_contents(resource_path('views/student/dashboard.blade.php'));

        $this->assertStringContainsString('.min-w-0 { min-width: 0 !important; }', $vue);
        $this->assertStringContainsString('piece-nom', $vue);
    }
}
