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
            AcademicSeeder::class,       // référentiel académique (facultés/départements/parcours)
            BachelierSeeder::class,
            AncienEtudiantSeeder::class,
            RequestTypeSeeder::class,    // types de documents par catégorie
            UserSeeder::class,           // compte super-administrateur
        ]);
    }
}
