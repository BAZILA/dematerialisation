<?php

namespace Database\Seeders;

use App\Models\Domaine;
use Illuminate\Database\Seeder;

class DomaineSeeder extends Seeder
{
    public function run(): void
    {
        $domaines = [
            ['Sciences et Technologies', 'ST'],
            ['Sciences Économiques et de Gestion', 'SEG'],
            ['Lettres, Langues et Arts', 'LLA'],
            ['Sciences Humaines et Sociales', 'SHS'],
            ['Sciences de la Santé', 'SS'],
            ['Droit et Sciences Politiques', 'DSP'],
        ];

        foreach ($domaines as [$nom, $sigle]) {
            Domaine::firstOrCreate(['nom' => $nom], ['sigle' => $sigle]);
        }
    }
}
