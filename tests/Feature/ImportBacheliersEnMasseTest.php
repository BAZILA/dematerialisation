<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Bachelier;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Chargement du fichier national des bacheliers (plus de 170 000 lignes).
 *
 * L'écran d'import ne pouvait pas le traiter : colonnes nommées
 * autrement (« code_sexe », « code_serie »), fichier en Windows-1252,
 * valeurs « NULL » écrites en toutes lettres, et plusieurs requêtes par
 * ligne dans une page web limitée à deux minutes. Ces tests reproduisent
 * le format RÉEL du fichier extrait de l'autre système.
 */
class ImportBacheliersEnMasseTest extends TestCase
{
    use RefreshDatabase;

    private array $fichiers = [];

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $f) {
            foreach ([$f, preg_replace('/\.csv$/', '.rejets.csv', $f), preg_replace('/\.csv$/', '.a-verifier.csv', $f)] as $x) {
                @unlink($x);
            }
        }
        parent::tearDown();
    }

    /**
     * Un fichier au format de l'extraction réelle : séparateur « ; »,
     * guillemets, Windows-1252, « NULL » en toutes lettres, et les
     * colonnes dans l'ORDRE de l'extraction, pas dans celui du modèle.
     */
    private function fichier(array $lignes): string
    {
        $entete = ['id', 'nom', 'prenom', 'code_sexe', 'date_naissance', 'lieu_naissance',
                   'code_mention_bac', 'numero_table', 'annee_bac', 'code_serie', 'telephone', 'email', 'adresse'];

        $texte = '"' . implode('";"', $entete) . "\"\r\n";
        foreach ($lignes as $l) {
            $texte .= implode(';', array_map(fn ($v) => $v === null ? 'NULL' : '"' . $v . '"', $l)) . "\r\n";
        }

        $chemin = tempnam(sys_get_temp_dir(), 'bach') . '.csv';
        file_put_contents($chemin, mb_convert_encoding($texte, 'Windows-1252', 'UTF-8'));
        $this->fichiers[] = $chemin;

        return $chemin;
    }

    /** Lignes d'essai. Les identités sont inventées. */
    private function promotion(): array
    {
        return [
            ['81492', 'KOSSI', 'Ama Élodie', 'F', '2007-03-14', 'Atakpamé', 'P', '1001', '2026', 'A4', null, null, null],
            ['81493', 'ADJO', 'Kodjo', 'M', '2006-11-02', 'Kara', 'AB', '1002', '2026', 'D', '90000000', 'kodjo@exemple.tg', 'Kara'],
            ['70010', 'TCHALA', 'Essowè', 'M', '2005-05-20', 'Sokodé', 'B', '1001', '2025', 'C', null, null, null],
            // Inutilisable : prénom vide
            ['70011', 'SANS', '', 'F', '2005-01-01', 'Lomé', 'P', '1003', '2025', 'D', null, null, null],
            // Année de bac absurde : chargée, année laissée vide, signalée
            ['70012', 'PALI', 'Yao', 'M', '2004-02-02', 'Bafilo', 'P', '1004', '20219', 'A4', null, null, null],
        ];
    }

    private function importer(string $fichier, array $options = []): int
    {
        return $this->artisan('uk:importer-bacheliers', ['fichier' => $fichier] + $options)->run();
    }

    /* ══════════════════════════════════════════════════════ */

    public function test_par_defaut_rien_n_est_ecrit(): void
    {
        $this->importer($this->fichier($this->promotion()));

        $this->assertSame(0, Bachelier::count(), 'Sans --importer, la commande ne doit rien écrire.');
    }

    public function test_le_fichier_reel_se_charge_colonnes_dans_le_desordre_et_accents_compris(): void
    {
        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--id-est-le-suivi' => true]);

        $this->assertSame(4, Bachelier::count(), '4 lignes utilisables sur 5 : la ligne sans prénom est écartée.');

        $ama = Bachelier::where('id_source', '81492')->first();
        $this->assertSame('KOSSI', $ama->nom);
        $this->assertSame('Ama Élodie', $ama->prenom, 'Les accents du fichier Windows-1252 doivent arriver intacts.');
        $this->assertSame('Atakpamé', $ama->lieu_naissance);
        $this->assertSame('F', $ama->sexe, '« code_sexe » doit être reconnu comme le sexe.');
        $this->assertSame('A4', $ama->serie_bac, '« code_serie » doit être reconnu comme la série.');
        $this->assertSame('P', $ama->mention_bac);
        $this->assertSame('2026', $ama->annee_bac);
        $this->assertSame('81492', $ama->numero_suivi, 'Avec --id-est-le-suivi, l\'id devient le n° de suivi.');
        $this->assertNull($ama->telephone, 'Le mot « NULL » ne doit pas être enregistré comme un texte.');
        $this->assertFalse($ama->is_registered);

        $kodjo = Bachelier::where('id_source', '81493')->first();
        $this->assertSame('kodjo@exemple.tg', $kodjo->email);
        $this->assertSame('Kara', $kodjo->adresse);

        // Même n° de table 1001, années différentes : deux personnes distinctes
        $this->assertSame(2, Bachelier::where('numero_table', '1001')->count(),
            'Le n° de table se répète d\'une année à l\'autre : il ne doit pas fusionner deux bacheliers.');

        $this->assertNull(Bachelier::where('id_source', '70012')->first()->annee_bac,
            'Une année de bac absurde est laissée vide plutôt qu\'enregistrée telle quelle.');
    }

    public function test_sans_l_option_le_numero_de_suivi_reste_vide(): void
    {
        $this->importer($this->fichier($this->promotion()), ['--importer' => true]);

        $this->assertSame(0, Bachelier::whereNotNull('numero_suivi')->count(),
            "Le n° de suivi ne doit jamais être deviné : on l'a déjà confondu une fois.");
        $this->assertSame(4, Bachelier::whereNotNull('id_source')->count());
    }

    public function test_relancer_le_chargement_ne_cree_aucun_doublon(): void
    {
        $fichier = $this->fichier($this->promotion());

        $this->importer($fichier, ['--importer' => true, '--id-est-le-suivi' => true]);
        $this->importer($fichier, ['--importer' => true, '--id-est-le-suivi' => true]);

        $this->assertSame(4, Bachelier::count());
    }

    public function test_on_peut_ne_charger_qu_une_annee(): void
    {
        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--annee' => ['2026']]);

        $this->assertSame(2, Bachelier::count());
        $this->assertSame(0, Bachelier::where('annee_bac', '!=', '2026')->count());
    }

    public function test_une_fiche_saisie_a_la_main_est_rattachee_sans_rien_perdre(): void
    {
        $this->socle();

        // Saisie à la main avant le chargement, et déjà dotée d'un compte
        $fiche = Bachelier::create([
            'nom' => 'KOSSI', 'prenom' => 'Ama Élodie', 'sexe' => 'F',
            'date_naissance' => '2007-03-14', 'lieu_naissance' => 'Atakpamé',
            'telephone' => '91111111', 'is_registered' => true,
        ]);
        $user = User::create([
            'numero_identifiant' => 'AKOSSI', 'email' => 'ama@test.local', 'password' => Hash::make('x1234567'),
            'role' => 'etudiant', 'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        Student::create([
            'user_id' => $user->id, 'bachelier_id' => $fiche->id, 'nom' => 'KOSSI', 'prenom' => 'Ama Élodie',
            'sexe' => 'F', 'date_naissance' => '2007-03-14', 'lieu_naissance' => 'Atakpamé',
        ]);

        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--id-est-le-suivi' => true]);

        $this->assertSame(1, Bachelier::where('nom', 'KOSSI')->count(),
            'La fiche saisie à la main ne doit pas être doublée par celle du fichier.');

        $fiche->refresh();
        $this->assertSame('81492', $fiche->id_source, 'Elle est rattachée au fichier.');
        $this->assertSame('81492', $fiche->numero_suivi, 'Elle reçoit le n° de suivi qui lui manquait.');
        $this->assertSame('91111111', $fiche->telephone, 'Ce qui y était déjà écrit est conservé.');
        $this->assertTrue($fiche->is_registered, 'Son compte n\'est pas détaché.');
        $this->assertSame($fiche->id, Student::first()->bachelier_id);
    }

    public function test_un_bachelier_charge_peut_creer_son_compte(): void
    {
        $this->socle();
        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--id-est-le-suivi' => true]);

        /* Saisie telle qu'un usager la tape : casse et espaces au hasard.
           Identité choisie SANS majuscule accentuée : la base des tests
           (SQLite) ne sait pas mettre « É » en minuscule, contrairement à
           MariaDB en production, qui de plus compare sans tenir compte
           des accents. Un « Élodie » échouerait ici sans échouer en ligne. */
        $this->post('/register/verify', [
            'type_etudiant' => 'nouveau', 'nom' => 'adjo', 'prenom' => ' KODJO ',
            'date_naissance' => '2006-11-02', 'lieu_naissance' => 'kara', 'sexe' => 'M',
        ])->assertRedirect(route('register.complete'));

        $this->assertSame(Bachelier::where('id_source', '81493')->value('id'), session('reg_bachelier_id'));
    }

    public function test_un_fichier_sans_les_colonnes_obligatoires_est_refuse_sans_rien_ecrire(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'bach') . '.csv';
        file_put_contents($chemin, "\"id\";\"nom\"\r\n\"1\";\"X\"\r\n");
        $this->fichiers[] = $chemin;

        $this->assertNotSame(0, $this->importer($chemin, ['--importer' => true]));
        $this->assertSame(0, Bachelier::count());
    }

    public function test_les_rapports_ne_sont_pas_ecrits_dans_la_racine_web(): void
    {
        $fichier = $this->fichier($this->promotion());
        $this->importer($fichier);

        $rejets = preg_replace('/\.csv$/', '.rejets.csv', $fichier);
        $this->assertFileExists($rejets, 'Les lignes écartées doivent être consignées.');
        $this->assertStringStartsNotWith(public_path(), $rejets);
        $this->assertStringContainsString('prénom vide', file_get_contents($rejets));
    }

    public function test_l_import_de_l_ecran_ne_confond_pas_deux_promotions_au_meme_numero_de_table(): void
    {
        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--id-est-le-suivi' => true]);
        $avant = Bachelier::where('id_source', '81492')->first();   // table 1001, bac 2026

        // Un petit fichier importé par l'écran : table 1001, mais bac 2024
        (new \App\Imports\BacheliersImport())->collection(collect([collect([
            'nom' => 'AUTRE', 'prenom' => 'Personne', 'code_sexe' => 'M',
            'date_naissance' => '2003-01-01', 'lieu_naissance' => 'Dapaong',
            'numero_table' => '1001', 'annee_bac' => '2024', 'code_serie' => 'D', 'telephone' => 'NULL',
        ])]));

        $this->assertSame('KOSSI', $avant->fresh()->nom,
            'La bachelière de 2026 ne doit pas être écrasée par un homonyme de numéro de table en 2024.');
        $autre = Bachelier::where('nom', 'AUTRE')->first();
        $this->assertNotNull($autre, 'Le bachelier de 2024 doit être créé à part.');
        $this->assertSame('D', $autre->serie_bac, '« code_serie » doit aussi être compris par l\'écran.');
        $this->assertNull($autre->telephone, '« NULL » en toutes lettres ne doit pas être enregistré.');
    }

    /* ══════════════════════════════════════════════════════
     |  Le n° de suivi sur la liste PDF de validation
     ══════════════════════════════════════════════════════ */

    public function test_la_liste_pdf_des_demandes_porte_le_numero_de_suivi(): void
    {
        $this->socle();
        $this->importer($this->fichier($this->promotion()), ['--importer' => true, '--id-est-le-suivi' => true]);
        $fiche = Bachelier::where('id_source', '81493')->first();

        $user = User::create([
            'numero_identifiant' => 'KADJO', 'email' => 'k@test.local', 'password' => Hash::make('x1234567'),
            'role' => 'etudiant', 'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        $etudiant = Student::create([
            'user_id' => $user->id, 'bachelier_id' => $fiche->id, 'nom' => 'ADJO', 'prenom' => 'Kodjo',
            'sexe' => 'M', 'date_naissance' => '2006-11-02', 'lieu_naissance' => 'Kara',
        ]);
        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
            'statut' => 'ouverte', 'is_active' => true,
        ]);
        StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot', 'parcours_id' => $this->parcours()->id,
            'annee_academique_id' => $annee->id, 'status' => 'soumis', 'version' => 1, 'submitted_at' => now(),
        ]);

        // Le PDF est compressé : on capte ce qu'on lui demande d'écrire.
        $capte = new class extends \App\Services\TableauPdfService {
            public array $entetes = [];
            public array $lignes = [];

            public function build(string $titre, array $entetes, iterable $lignes, string $genereePar = '',
                string $filtres = '', array $poids = [], string $orientation = 'L'): string
            {
                $this->entetes = $entetes;
                $this->lignes = collect($lignes)->all();

                return '%PDF-1.4';
            }
        };
        $this->app->instance(\App\Services\TableauPdfService::class, $capte);

        $this->actingAs($this->superAdmin())
            ->get('/admin/requests-export/depot?format=pdf&annee_id=all')
            ->assertOk();

        $this->assertSame('N° suivi', $capte->entetes[0], 'Le n° de suivi doit ouvrir la ligne.');
        $this->assertSame('81493', $capte->lignes[0][0]);
        $this->assertCount(count($capte->entetes), $capte->lignes[0], 'Chaque ligne a autant de cases que d\'en-têtes.');
    }
}
