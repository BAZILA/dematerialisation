<?php

namespace Database\Seeders;

use App\Models\AncienEtudiant;
use Illuminate\Database\Seeder;

class AncienEtudiantSeeder extends Seeder
{
    public function run(): void
    {
        $anciens = [
            [
                'numero_carte'        => 'CARTE0000001',
                'nom'                 => 'AGBEKO',
                'prenom'              => 'Yves',
                'date_naissance'      => '2001-05-14',
                'lieu_naissance'      => 'Tsévié',
                'sexe'                => 'M',
                'specialite'          => 'Informatique',
                'derniere_universite' => 'Université de Lomé',
                'is_registered'       => false,
            ],
            [
                'numero_carte'        => 'CARTE0000002',
                'nom'                 => 'TETTEH',
                'prenom'              => 'Akua',
                'date_naissance'      => '2002-01-30',
                'lieu_naissance'      => 'Sokodé',
                'sexe'                => 'F',
                'specialite'          => 'Gestion',
                'derniere_universite' => 'Université de Kara',
                'is_registered'       => false,
            ],
        ];

        foreach ($anciens as $a) {
            AncienEtudiant::firstOrCreate(['numero_carte' => $a['numero_carte']], $a);
        }
    }
}
