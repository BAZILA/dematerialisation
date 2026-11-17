<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chaque niveau a ses propres pièces.
 *
 * Avant, une pièce sans rattachement était réclamée à tout le monde : un
 * master devait fournir son attestation de réussite au Bac, un nouveau
 * bachelier un diplôme de Licence. Désormais une pièce appartient à UN
 * niveau. « Commune » se lit à l'intérieur de ce niveau — tous ses
 * parcours — et jamais au-delà.
 */
class PorteeDesPiecesTest extends TestCase
{
    use RefreshDatabase;

    /** Un parcours dans le niveau demandé. */
    private function parcoursDe(string $grade, string $nom): Parcours
    {
        $faculte = Faculte::firstOrCreate(
            ['sigle' => 'FDS'],
            ['nom' => 'Faculté des Sciences', 'type' => 'faculte', 'is_active' => true]
        );
        $departement = Departement::firstOrCreate(
            ['sigle' => 'DINF'],
            ['faculte_id' => $faculte->id, 'nom' => 'Département Informatique', 'is_active' => true]
        );

        return Parcours::create([
            'departement_id' => $departement->id,
            'nom' => $nom, 'sigle' => strtoupper(substr($nom, 0, 4)) . $grade[0],
            'grade' => $grade, 'is_active' => true,
        ]);
    }

    private function piece(string $libelle, string $grade, string $categorie = 'depot'): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => $categorie, 'grade' => $grade,
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);
    }

    /* ── Le niveau cloisonne ────────────────────────── */

    public function test_une_piece_de_licence_n_est_jamais_reclamee_a_un_master(): void
    {
        $this->socle();
        $licence = $this->parcoursDe('licence', 'Informatique');
        $master  = $this->parcoursDe('master', 'Génie Logiciel');

        $this->piece('Attestation de réussite au Bac', 'licence');

        $pourLeMaster = RequestType::forCategory('depot', 'both', 'master', $master->id);

        $this->assertCount(0, $pourLeMaster,
            "Un master ne doit pas se voir réclamer une pièce de licence.");

        $pourLaLicence = RequestType::forCategory('depot', 'both', 'licence', $licence->id);
        $this->assertCount(1, $pourLaLicence);
    }

    public function test_chaque_niveau_a_sa_propre_liste(): void
    {
        $this->socle();
        $licence = $this->parcoursDe('licence', 'Informatique');
        $master  = $this->parcoursDe('master', 'Génie Logiciel');

        $this->piece('Attestation de réussite au Bac', 'licence');
        $this->piece('Relevé de notes du Bac', 'licence');
        $this->piece('Diplôme de Licence', 'master');

        $this->assertSame(2, RequestType::forCategory('depot', 'both', 'licence', $licence->id)->count());
        $this->assertSame(1, RequestType::forCategory('depot', 'both', 'master', $master->id)->count());
    }

    public function test_sans_niveau_connu_rien_n_est_reclame(): void
    {
        $this->socle();
        $this->piece('Acte de naissance', 'licence');

        // Plutôt qu'un dossier exigeant les pièces d'un autre cursus,
        // on préfère ne rien réclamer tant que le niveau est inconnu.
        $this->assertCount(0, RequestType::forCategory('depot', 'both', null));
    }

    /* ── « Commune » = tout le niveau ───────────────── */

    public function test_une_piece_commune_vaut_pour_tous_les_parcours_du_niveau(): void
    {
        $this->socle();
        $info   = $this->parcoursDe('licence', 'Informatique');
        $maths  = $this->parcoursDe('licence', 'Mathématiques');

        $piece = $this->piece('Acte de naissance', 'licence');

        $this->assertTrue($piece->estCommune());
        $this->assertCount(1, RequestType::forCategory('depot', 'both', 'licence', $info->id));
        $this->assertCount(1, RequestType::forCategory('depot', 'both', 'licence', $maths->id));
    }

    public function test_une_piece_restreinte_ne_touche_que_ses_parcours(): void
    {
        $this->socle();
        $staps = $this->parcoursDe('licence', 'STAPS');
        $info  = $this->parcoursDe('licence', 'Informatique');

        $piece = $this->piece("Certificat médical d'aptitude", 'licence');
        $piece->parcours()->attach($staps->id);

        $this->assertFalse($piece->fresh(['parcours'])->estCommune());
        $this->assertCount(1, RequestType::forCategory('depot', 'both', 'licence', $staps->id));
        $this->assertCount(0, RequestType::forCategory('depot', 'both', 'licence', $info->id),
            "Les autres parcours du niveau ne doivent pas la voir.");
    }

    public function test_le_libelle_de_portee_dit_le_niveau(): void
    {
        $this->socle();
        $staps = $this->parcoursDe('licence', 'STAPS');

        $commune = $this->piece('Acte de naissance', 'licence');
        $this->assertStringContainsString('Licence', $commune->portee_label);

        $propre = $this->piece("Certificat médical", 'licence');
        $propre->parcours()->attach($staps->id);
        $this->assertStringContainsString('STAPS', $propre->fresh(['parcours'])->portee_label);
    }

    /* ── Enregistrement depuis l'écran d'un niveau ──── */

    public function test_un_parcours_d_un_autre_niveau_est_ecarte(): void
    {
        $this->socle();
        $licence = $this->parcoursDe('licence', 'Informatique');
        $master  = $this->parcoursDe('master', 'Génie Logiciel');

        $piece = $this->piece('Acte de naissance', 'licence');

        // Requête forgée : on tente de rattacher un parcours de master à
        // une pièce de licence. Retenu, il rendrait la pièce invisible.
        $this->actingAs($this->superAdmin())->put("/admin/request-types/{$piece->id}", [
            'label' => 'Acte de naissance', 'category' => 'depot',
            'grade' => 'licence', 'type_etudiant' => 'both',
            'is_required' => 1, 'is_active' => 1,
            'portee' => 'specifique', 'parcours_ids' => [$master->id],
        ]);

        $this->assertSame(0, $piece->fresh()->parcours()->count(),
            "Un parcours hors niveau ne doit pas être retenu.");
        $this->assertTrue($piece->fresh(['parcours'])->estCommune(),
            "Sans parcours valide, la pièce redevient commune à son niveau.");
    }

    public function test_changer_une_piece_de_licence_ne_touche_pas_le_master(): void
    {
        $this->socle();
        $this->parcoursDe('licence', 'Informatique');
        $this->parcoursDe('master', 'Génie Logiciel');

        $enLicence = $this->piece('Acte de naissance', 'licence');
        $enMaster  = $this->piece('Acte de naissance', 'master');

        $this->actingAs($this->superAdmin())->put("/admin/request-types/{$enLicence->id}", [
            'label' => 'Acte de naissance (original)', 'category' => 'depot',
            'grade' => 'licence', 'type_etudiant' => 'both', 'portee' => 'commune',
        ]);

        $this->assertSame('Acte de naissance (original)', $enLicence->fresh()->label);
        $this->assertSame('Acte de naissance', $enMaster->fresh()->label,
            "Les deux fiches sont indépendantes : l'une ne suit pas l'autre.");
        $this->assertFalse((bool) $enLicence->fresh()->is_active,
            "Case décochée = champ absent : la pièce doit se désactiver.");
        $this->assertTrue((bool) $enMaster->fresh()->is_active,
            "La désactivation en licence ne touche pas le master.");
    }

    /* ── Recopie vers un autre niveau ───────────────── */

    public function test_recopier_les_pieces_vers_un_autre_niveau(): void
    {
        $this->socle();
        $this->parcoursDe('licence', 'Informatique');
        $this->parcoursDe('master', 'Génie Logiciel');

        $this->piece('Acte de naissance', 'licence');
        $this->piece("Photo d'identité", 'licence');

        $this->actingAs($this->superAdmin())
            ->post('/admin/request-types/niveau/licence/dupliquer', [
                'vers' => 'master', 'category' => 'depot',
            ]);

        $this->assertSame(2, RequestType::where('grade', 'master')->count());
        $this->assertSame(2, RequestType::where('grade', 'licence')->count(),
            "La recopie ne doit rien retirer au niveau d'origine.");
    }

    public function test_recopier_deux_fois_n_empile_pas_les_doublons(): void
    {
        $this->socle();
        $this->parcoursDe('licence', 'Informatique');
        $this->piece('Acte de naissance', 'licence');

        $admin = $this->superAdmin();
        foreach ([1, 2] as $fois) {
            $this->actingAs($admin)->post('/admin/request-types/niveau/licence/dupliquer', [
                'vers' => 'master', 'category' => 'depot',
            ]);
        }

        $this->assertSame(1, RequestType::where('grade', 'master')->count(),
            "Relancer la recopie ne doit pas dupliquer ce qui est déjà là.");
    }

    public function test_la_recopie_ne_reprend_pas_la_restriction_par_parcours(): void
    {
        $this->socle();
        $staps = $this->parcoursDe('licence', 'STAPS');
        $this->parcoursDe('master', 'Génie Logiciel');

        $piece = $this->piece('Certificat médical', 'licence');
        $piece->parcours()->attach($staps->id);

        $this->actingAs($this->superAdmin())
            ->post('/admin/request-types/niveau/licence/dupliquer', [
                'vers' => 'master', 'category' => 'depot',
            ]);

        $copie = RequestType::where('grade', 'master')->first();
        $this->assertNotNull($copie);
        $this->assertTrue($copie->estCommune(),
            "Les parcours de licence n'existent pas en master : la copie naît commune.");
    }

    public function test_on_ne_recopie_pas_un_niveau_sur_lui_meme(): void
    {
        $this->socle();
        $this->parcoursDe('licence', 'Informatique');
        $this->piece('Acte de naissance', 'licence');

        $this->actingAs($this->superAdmin())
            ->post('/admin/request-types/niveau/licence/dupliquer', [
                'vers' => 'licence', 'category' => 'depot',
            ])->assertSessionHasErrors();

        $this->assertSame(1, RequestType::where('grade', 'licence')->count());
    }

    /* -- Bout en bout : le dossier d'un master ------- */

    public function test_un_depot_de_master_n_exige_pas_les_pieces_de_licence(): void
    {
        $this->socle();
        $master = $this->parcoursDe('master', 'Genie Logiciel');

        // Le cas qui a motive la refonte : ces deux pieces etaient
        // reclamees a tout le monde, master compris.
        $this->piece('Attestation de reussite au Bac', 'licence');
        $this->piece('Releve de notes du Bac', 'licence');
        $this->piece('Diplome de Licence', 'master');

        $annee = \App\Models\AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);

        $user = \App\Models\User::create([
            'numero_identifiant' => 'MAS001', 'email' => 'mas001@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('secret1234'),
            'role' => 'etudiant', 'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');
        $etudiant = \App\Models\Student::create([
            'user_id' => $user->id, 'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]);

        $dossier = \App\Models\StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'parcours_id' => $master->id, 'annee_academique_id' => $annee->id,
            'status' => 'brouillon', 'version' => 1,
        ]);

        $this->assertSame(['Diplome de Licence'], $dossier->typesAttendus()->pluck('label')->all(),
            "Un master ne doit plus se voir reclamer les pieces d'entree en licence.");
        $this->assertSame(['Diplome de Licence'],
            $dossier->piecesObligatoiresManquantes()->pluck('label')->all());
    }

    /* ── Candidature : le niveau vient des vœux ─────── */

    public function test_une_candidature_n_appelle_que_les_pieces_de_son_niveau(): void
    {
        $this->socle();
        $licence = $this->parcoursDe('licence', 'Informatique');
        $master  = $this->parcoursDe('master', 'Génie Logiciel');

        $this->piece('Relevé du Bac', 'licence', 'candidature');
        $this->piece('Diplôme de Licence', 'master', 'candidature');

        $pourLicence = RequestType::pourCandidature([$licence->id]);
        $pourMaster  = RequestType::pourCandidature([$master->id]);

        $this->assertSame(['Relevé du Bac'], $pourLicence->pluck('label')->all());
        $this->assertSame(['Diplôme de Licence'], $pourMaster->pluck('label')->all());
    }

    public function test_sans_voeu_aucune_piece_de_candidature(): void
    {
        $this->socle();
        $this->parcoursDe('licence', 'Informatique');
        $this->piece('Relevé du Bac', 'licence', 'candidature');

        $this->assertCount(0, RequestType::pourCandidature([]),
            "Tant que le niveau est inconnu, rien ne peut être réclamé.");
    }
}
