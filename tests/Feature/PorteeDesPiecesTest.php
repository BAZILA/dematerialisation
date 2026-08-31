<?php

namespace Tests\Feature;

use App\Models\RequestType;
use App\Models\RequestTypeGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une pièce peut être réclamée à tout le monde, à un grade entier
 * (« tous les masters »), à des parcours nommés, ou aux deux à la fois.
 * Les portées se cumulent, elles ne se remplacent pas.
 */
class PorteeDesPiecesTest extends TestCase
{
    use RefreshDatabase;

    private function pieceCommune(string $libelle = 'Acte de naissance'): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'candidature',
            'is_required' => true, 'is_active' => true,
        ]);
    }

    private function pourGrades(RequestType $type, array $grades): RequestType
    {
        foreach ($grades as $g) {
            RequestTypeGrade::create(['request_type_id' => $type->id, 'grade' => $g]);
        }

        return $type->fresh(['grades', 'parcours']);
    }

    public function test_une_piece_sans_restriction_est_commune(): void
    {
        $this->socle();
        $piece = $this->pieceCommune();

        $this->assertTrue($piece->estCommune());
        $this->assertSame('Commune à tous les parcours', $piece->portee_label);
    }

    public function test_une_piece_liee_a_un_grade_n_est_plus_commune(): void
    {
        $this->socle();
        $piece = $this->pourGrades($this->pieceCommune('Mémoire de licence'), ['master']);

        $this->assertFalse($piece->estCommune(),
            "Réservée aux masters, elle ne doit pas être réclamée à tout le monde.");
        $this->assertStringContainsString('Master', $piece->portee_label);
    }

    public function test_le_grade_evite_d_enumerer_les_parcours(): void
    {
        $this->socle();

        $licence = $this->parcours('Informatique');           // grade licence
        $master  = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $commune = $this->pieceCommune('Acte de naissance');
        $pourMaster = $this->pourGrades($this->pieceCommune('Mémoire'), ['master']);

        // Un candidat en licence : la pièce de master ne le concerne pas
        $attendusLicence = RequestType::pourCandidature([$licence->id])->pluck('id');
        $this->assertTrue($attendusLicence->contains($commune->id));
        $this->assertFalse($attendusLicence->contains($pourMaster->id));

        // Un candidat en master : les deux lui sont réclamées
        $attendusMaster = RequestType::pourCandidature([$master->id])->pluck('id');
        $this->assertTrue($attendusMaster->contains($commune->id));
        $this->assertTrue($attendusMaster->contains($pourMaster->id),
            "Le master doit hériter de la pièce posée sur son grade.");
    }

    public function test_grade_et_parcours_se_cumulent(): void
    {
        $this->socle();

        $masterInfo = $this->parcours('Master Informatique');
        $masterInfo->update(['grade' => 'master']);
        $masterGestion = $this->parcours('Master Gestion');
        $masterGestion->update(['grade' => 'master']);

        // « Tous les masters » ET « en plus, le master informatique »
        $tousMasters = $this->pourGrades($this->pieceCommune('Relevé Master'), ['master']);
        $propreInfo  = $this->pieceCommune('Portfolio de code');
        $propreInfo->parcours()->attach($masterInfo->id);

        $pourInfo = RequestType::pourCandidature([$masterInfo->id])->pluck('id');
        $this->assertTrue($pourInfo->contains($tousMasters->id));
        $this->assertTrue($pourInfo->contains($propreInfo->id),
            "Les deux portées se cumulent sur le master informatique.");

        $pourGestion = RequestType::pourCandidature([$masterGestion->id])->pluck('id');
        $this->assertTrue($pourGestion->contains($tousMasters->id));
        $this->assertFalse($pourGestion->contains($propreInfo->id),
            "Le portfolio ne concerne que le master informatique.");
    }

    public function test_plusieurs_grades_sur_une_meme_piece(): void
    {
        $this->socle();

        $doctorat = $this->parcours('Doctorat Sciences');
        $doctorat->update(['grade' => 'doctorat']);
        $licence = $this->parcours('Licence Lettres');

        $piece = $this->pourGrades($this->pieceCommune('Projet de recherche'), ['master', 'doctorat']);

        $this->assertTrue(RequestType::pourCandidature([$doctorat->id])->pluck('id')->contains($piece->id));
        $this->assertFalse(RequestType::pourCandidature([$licence->id])->pluck('id')->contains($piece->id));
    }

    public function test_la_suppression_d_une_piece_emporte_ses_grades(): void
    {
        $this->socle();
        $piece = $this->pourGrades($this->pieceCommune('Temporaire'), ['master']);
        $id = $piece->id;

        $piece->delete();

        $this->assertDatabaseMissing('request_type_grade', ['request_type_id' => $id]);
    }
}
