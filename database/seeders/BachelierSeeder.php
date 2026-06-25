<?php
// ─────────────────────────────────────────────────────────────────────────────
// FILE 1 : database/seeders/BachelierSeeder.php
// ─────────────────────────────────────────────────────────────────────────────
namespace Database\Seeders;

use App\Models\Bachelier;
use Illuminate\Database\Seeder;

class BachelierSeeder extends Seeder
{
    public function run(): void
    {
        $bacheliers = [
            [
                'nom'            => 'MENSAH',
                'prenom'         => 'Kofi',
                'date_naissance' => '2004-03-15',
                'lieu_naissance' => 'Lomé',
                'sexe'           => 'M',
                'serie_bac'      => 'D',
                'annee_bac'      => '2023',
                'numero_releve'  => 'BAC2023001',
                'academie'       => 'Lomé-Maritime',
                'is_registered'  => false,
            ],
            [
                'nom'            => 'AZIATO',
                'prenom'         => 'Ama',
                'date_naissance' => '2004-07-22',
                'lieu_naissance' => 'Kpalimé',
                'sexe'           => 'F',
                'serie_bac'      => 'C',
                'annee_bac'      => '2023',
                'numero_releve'  => 'BAC2023002',
                'academie'       => 'Plateaux',
                'is_registered'  => false,
            ],
            [
                'nom'            => 'DOSSOU',
                'prenom'         => 'Sévi',
                'date_naissance' => '2003-11-08',
                'lieu_naissance' => 'Atakpamé',
                'sexe'           => 'M',
                'serie_bac'      => 'A4',
                'annee_bac'      => '2023',
                'numero_releve'  => 'BAC2023003',
                'academie'       => 'Centrale',
                'is_registered'  => false,
            ],
        ];

        foreach ($bacheliers as $b) {
            Bachelier::firstOrCreate(['numero_releve' => $b['numero_releve']], $b);
        }
    }
}
