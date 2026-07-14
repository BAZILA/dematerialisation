<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Periode;
use Illuminate\Database\Seeder;

class AnneeAcademiqueSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $startYear = $now->month >= 9 ? $now->year : $now->year - 1;
        $libelle = $startYear . '-' . ($startYear + 1);

        $annee = AnneeAcademique::firstOrCreate(
            ['libelle' => $libelle],
            [
                'date_debut' => "{$startYear}-09-01",
                'date_fin'   => ($startYear + 1) . '-08-31',
                'statut'     => 'ouverte',
                'is_active'  => true,
            ]
        );

        foreach (array_keys(Periode::$operations) as $op) {
            Periode::firstOrCreate(
                ['annee_academique_id' => $annee->id, 'operation' => $op],
                ['is_open' => true]
            );
        }
    }
}
