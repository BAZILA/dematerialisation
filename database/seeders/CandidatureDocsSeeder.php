<?php

namespace Database\Seeders;

use App\Models\RequestType;
use Illuminate\Database\Seeder;

class CandidatureDocsSeeder extends Seeder
{
    public function run(): void
    {
        $docs = [
            ['Acte de naissance', true],
            ['Relevé de notes du Baccalauréat', true],
            ['Diplôme ou attestation du Baccalauréat', true],
            ["Photo d'identité", false],
        ];

        $ordre = 1;
        foreach ($docs as [$label, $req]) {
            // Une piece appartient a un niveau : ce jeu de depart vise la licence.
            RequestType::firstOrCreate(
                ['category' => 'candidature', 'grade' => 'licence', 'label' => $label],
                ['type_etudiant' => 'both', 'is_required' => $req, 'is_active' => true, 'has_stamp' => false, 'order' => $ordre++]
            );
        }
    }
}
