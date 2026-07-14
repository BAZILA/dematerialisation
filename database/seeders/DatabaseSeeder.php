<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,           // rôles & permissions (Spatie)
            SettingSeeder::class,        // réglages de la plateforme
            DomaineSeeder::class,        // domaines (transverses, verso des dépôts)
            AcademicSeeder::class,       // référentiel académique (facultés/départements/parcours)
            AnneeAcademiqueSeeder::class, // années académiques + fenêtres d'ouverture
            BachelierSeeder::class,
            AncienEtudiantSeeder::class,
            RequestTypeSeeder::class,    // types de documents par catégorie
            UserSeeder::class,           // compte super-administrateur
        ]);
    }
}
