<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BachelierSeeder::class,
            AncienEtudiantSeeder::class,
            DocumentTypeSeeder::class,   // pour l'ancien flux
            RequestTypeSeeder::class,    // pour le nouveau flux
            UserSeeder::class,
        ]);
    }
}
