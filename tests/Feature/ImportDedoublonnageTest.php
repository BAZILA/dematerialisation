<?php

namespace Tests\Feature;

use App\Imports\AnciensImport;
use App\Imports\BacheliersImport;
use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Le référentiel porte 1165 fiches. Un import qui duplique ou qui vide
 * des colonnes ne se voit pas tout de suite — et ne se rattrape pas.
 */
class ImportDedoublonnageTest extends TestCase
{
    use RefreshDatabase;

    private function lignes(array $lignes): Collection
    {
        return collect($lignes)->map(fn ($l) => collect($l));
    }

    private function importerBacheliers(array $lignes): BacheliersImport
    {
        $import = new BacheliersImport();
        $import->collection($this->lignes($lignes));

        return $import;
    }

    private function bachelier(array $ecrase = []): array
    {
        return array_merge([
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ], $ecrase);
    }

    /* ── Ce qui dupliquait ──────────────────────────── */

    public function test_un_second_fichier_qui_ajoute_le_numero_de_suivi_ne_duplique_pas(): void
    {
        // Premier fichier : la fiche n'a qu'un numéro de relevé.
        $this->importerBacheliers([$this->bachelier(['numero_releve' => 'REL-001'])]);
        $this->assertSame(1, Bachelier::count());

        // Second fichier : la même personne, avec en plus son numéro de suivi.
        // L'ancienne logique cherchait sur « numero_suivi », encore vide en
        // base, ne trouvait rien, et créait un doublon.
        $import = $this->importerBacheliers([
            $this->bachelier(['numero_releve' => 'REL-001', 'numero_suivi' => 'SUI-001']),
        ]);

        $this->assertSame(1, Bachelier::count(), 'La fiche a été dupliquée.');
        $this->assertSame(1, $import->updated);
        $this->assertSame('SUI-001', Bachelier::first()->numero_suivi);
    }

    public function test_reimporter_un_fichier_sans_identifiant_ne_duplique_pas(): void
    {
        $ligne = [$this->bachelier()];   // aucun numéro

        $this->importerBacheliers($ligne);
        $import = $this->importerBacheliers($ligne);

        $this->assertSame(1, Bachelier::count(),
            "Sans repli, réimporter le même fichier dupliquait toute la promotion.");
        $this->assertSame(1, $import->updated);
    }

    public function test_deux_personnes_distinctes_restent_distinctes(): void
    {
        $this->importerBacheliers([
            $this->bachelier(['numero_releve' => 'REL-001']),
            $this->bachelier(['nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
                              'numero_releve' => 'REL-002']),
        ]);

        $this->assertSame(2, Bachelier::count());
    }

    public function test_deux_homonymes_de_dates_differentes_restent_distincts(): void
    {
        $this->importerBacheliers([
            $this->bachelier(),
            $this->bachelier(['date_naissance' => '2005-03-04']),
        ]);

        $this->assertSame(2, Bachelier::count(),
            "La date de naissance sépare deux homonymes.");
    }

    /* ── Ce qui effaçait ────────────────────────────── */

    public function test_une_colonne_absente_n_efface_pas_la_valeur_existante(): void
    {
        $this->importerBacheliers([
            $this->bachelier(['numero_releve' => 'REL-001', 'telephone' => '90112233',
                              'email' => 'kodjo@test.local', 'serie_bac' => 'D']),
        ]);

        // Second fichier plus court : ni téléphone, ni courriel, ni série.
        $this->importerBacheliers([$this->bachelier(['numero_releve' => 'REL-001'])]);

        $fiche = Bachelier::first();
        $this->assertSame('90112233', $fiche->telephone);
        $this->assertSame('kodjo@test.local', $fiche->email);
        $this->assertSame('D', $fiche->serie_bac);
    }

    public function test_une_valeur_fournie_ecrase_bien_l_ancienne(): void
    {
        $this->importerBacheliers([
            $this->bachelier(['numero_releve' => 'REL-001', 'telephone' => '90112233']),
        ]);
        $this->importerBacheliers([
            $this->bachelier(['numero_releve' => 'REL-001', 'telephone' => '99887766']),
        ]);

        $this->assertSame('99887766', Bachelier::first()->telephone);
    }

    /* ── Lignes refusées ────────────────────────────── */

    public function test_une_ligne_incomplete_est_ignoree_et_listee(): void
    {
        $import = $this->importerBacheliers([
            $this->bachelier(['nom' => '']),           // nom manquant
            $this->bachelier(['sexe' => 'X']),         // sexe invalide
            $this->bachelier(['numero_releve' => 'REL-009']),
        ]);

        $this->assertSame(1, Bachelier::count());
        $this->assertSame(2, $import->skipped);
        $this->assertCount(2, $import->errors);
    }

    /* ── Anciens étudiants ──────────────────────────── */

    public function test_les_anciens_se_dedoublonnent_sur_le_numero_de_carte(): void
    {
        $ligne = [[
            'numero_carte' => 'CARTE-001', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'telephone' => '90556677',
        ]];

        $import = new AnciensImport();
        $import->collection($this->lignes($ligne));
        $import2 = new AnciensImport();
        $import2->collection($this->lignes($ligne));

        $this->assertSame(1, AncienEtudiant::count());
        $this->assertSame(1, $import2->updated);
    }

    public function test_un_ancien_sans_numero_de_carte_est_refuse(): void
    {
        $import = new AnciensImport();
        $import->collection($this->lignes([[
            'numero_carte' => '', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]]));

        $this->assertSame(0, AncienEtudiant::count());
        $this->assertSame(1, $import->skipped);
    }

    public function test_un_second_fichier_court_ne_vide_pas_la_fiche_d_un_ancien(): void
    {
        $complet = [[
            'numero_carte' => 'CARTE-002', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'telephone' => '90556677', 'adresse' => 'Kara centre', 'specialite' => 'Droit',
        ]];
        $court = [[
            'numero_carte' => 'CARTE-002', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]];

        (new AnciensImport())->collection($this->lignes($complet));
        (new AnciensImport())->collection($this->lignes($court));

        $fiche = AncienEtudiant::first();
        $this->assertSame('90556677', $fiche->telephone);
        $this->assertSame('Kara centre', $fiche->adresse);
        $this->assertSame('Droit', $fiche->specialite);
    }
}
