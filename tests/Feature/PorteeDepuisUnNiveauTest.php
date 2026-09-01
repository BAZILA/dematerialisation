<?php

namespace Tests\Feature;

use App\Models\RequestType;
use App\Models\RequestTypeGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les pièces s'éditent depuis l'écran d'un niveau, où le formulaire ne
 * montre que ce niveau et ses parcours. Enregistrer depuis la licence
 * ne doit RIEN changer aux portées master ou doctorat : elles ne sont
 * pas dans la requête, et un sync naïf les effacerait en silence.
 */
class PorteeDepuisUnNiveauTest extends TestCase
{
    use RefreshDatabase;

    private function piece(string $libelle = 'Acte de naissance'): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'candidature',
            'is_required' => true, 'is_active' => true,
        ]);
    }

    private function enregistrer(RequestType $type, array $champs): void
    {
        $this->actingAs($this->superAdmin())->put(
            route('admin.request-types.update', $type),
            array_merge([
                'label'         => $type->label,
                'category'      => 'candidature',
                'type_etudiant' => 'both',
                'portee'        => 'specifique',
            ], $champs)
        );
    }

    public function test_editer_depuis_la_licence_preserve_le_master(): void
    {
        $this->socle();

        $licence = $this->parcours('Informatique');            // grade licence
        $master  = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $piece = $this->piece();
        $piece->parcours()->attach([$licence->id, $master->id]);
        RequestTypeGrade::create(['request_type_id' => $piece->id, 'grade' => 'master']);

        // Depuis la licence : on décoche le parcours de licence.
        // Le formulaire n'a jamais montré le master.
        $this->enregistrer($piece, [
            'niveau'       => 'licence',
            'parcours_ids' => [],
            'grades'       => [],
        ]);

        $piece = $piece->fresh(['parcours', 'grades']);

        $this->assertFalse($piece->parcours->contains('id', $licence->id),
            "Le parcours de licence décoché doit bien être retiré.");
        $this->assertTrue($piece->parcours->contains('id', $master->id),
            "Le parcours de master, absent du formulaire, doit survivre.");
        $this->assertContains('master', $piece->gradesCodes(),
            "La portée « tout Master » doit survivre à une édition faite en licence.");
    }

    public function test_on_peut_ajouter_une_portee_sans_toucher_aux_autres(): void
    {
        $this->socle();

        $licence = $this->parcours('Lettres');
        $master  = $this->parcours('Droit');
        $master->update(['grade' => 'master']);

        $piece = $this->piece('Relevé de notes');
        RequestTypeGrade::create(['request_type_id' => $piece->id, 'grade' => 'master']);

        $this->enregistrer($piece, [
            'niveau'       => 'licence',
            'parcours_ids' => [$licence->id],
            'grades'       => ['licence'],
        ]);

        $piece = $piece->fresh(['parcours', 'grades']);

        $this->assertTrue($piece->parcours->contains('id', $licence->id));
        $this->assertEqualsCanonicalizing(['licence', 'master'], $piece->gradesCodes(),
            "Les deux niveaux doivent coexister après l'édition.");
    }

    public function test_un_grade_etranger_soumis_est_ignore(): void
    {
        $this->socle();
        $this->parcours('Lettres');

        $piece = $this->piece('Pièce test');

        // Requête forgée : on tente de poser « master » depuis la licence
        $this->enregistrer($piece, [
            'niveau'       => 'licence',
            'parcours_ids' => [],
            'grades'       => ['licence', 'master'],
        ]);

        $this->assertSame(['licence'], $piece->fresh(['grades'])->gradesCodes(),
            "Depuis la licence, on ne pose de règle que sur la licence.");
    }

    public function test_l_edition_globale_reste_possible(): void
    {
        $this->socle();
        $master = $this->parcours('Droit');
        $master->update(['grade' => 'master']);

        $piece = $this->piece('Pièce globale');
        RequestTypeGrade::create(['request_type_id' => $piece->id, 'grade' => 'master']);

        // Sans contexte de niveau : comportement d'origine, on remplace tout
        $this->enregistrer($piece, [
            'parcours_ids' => [],
            'grades'       => ['doctorat'],
        ]);

        $this->assertSame(['doctorat'], $piece->fresh(['grades'])->gradesCodes());
    }

    public function test_repasser_une_piece_en_commune_depuis_un_niveau(): void
    {
        $this->socle();
        $licence = $this->parcours('Lettres');

        $piece = $this->piece('Photo');
        $piece->parcours()->attach($licence->id);
        RequestTypeGrade::create(['request_type_id' => $piece->id, 'grade' => 'licence']);

        $this->enregistrer($piece, ['niveau' => 'licence', 'portee' => 'commune']);

        $piece = $piece->fresh(['parcours', 'grades']);
        $this->assertTrue($piece->estCommune(),
            "Choisir « Commune » depuis un niveau doit lever ses restrictions.");
    }
}
