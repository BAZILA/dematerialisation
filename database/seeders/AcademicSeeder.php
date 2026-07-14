<?php

namespace Database\Seeders;

use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Parcours;
use Illuminate\Database\Seeder;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Faculté des Sciences et Techniques', 'FaST', 'faculte', [
                ['Informatique', 'INFO', [['Informatique', 'L-INFO', 'licence'], ['Génie Logiciel', 'M-GL', 'master']]],
                ['Mathématiques', 'MATH', [['Mathématiques', 'L-MATH', 'licence']]],
            ]],
            ['Faculté des Sciences Économiques et de Gestion', 'FASEG', 'faculte', [
                ['Gestion', 'GES', [['Gestion des Entreprises', 'L-GE', 'licence']]],
                ['Économie', 'ECO', [['Sciences Économiques', 'L-ECO', 'licence']]],
            ]],
            ['Institut Universitaire de Technologie', 'IUT', 'institut', [
                ['Génie Électrique', 'GEII', [['Génie Électrique et Informatique', 'DUT-GEII', 'licence']]],
            ]],
        ];

        foreach ($data as [$fNom, $fSigle, $fType, $deps]) {
            $fac = Faculte::firstOrCreate(['nom' => $fNom], ['sigle' => $fSigle, 'type' => $fType]);
            foreach ($deps as [$dNom, $dSigle, $parcours]) {
                $dep = Departement::firstOrCreate(['faculte_id' => $fac->id, 'nom' => $dNom], ['sigle' => $dSigle]);
                foreach ($parcours as [$pNom, $pSigle, $grade]) {
                    Parcours::firstOrCreate(
                        ['departement_id' => $dep->id, 'nom' => $pNom, 'grade' => $grade],
                        ['sigle' => $pSigle]
                    );
                }
            }
        }
    }
}
