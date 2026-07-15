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
            ProcedureSeeder::class,      // opérations/procédures (dépôt, attestation, candidature…)
            DomaineSeeder::class,        // domaines (transverses, verso des dépôts)
            AcademicSeeder::class,       // référentiel académique (facultés/départements/parcours)
            AnneeAcademiqueSeeder::class, // années académiques + fenêtres d'ouverture
            BachelierSeeder::class,
            AncienEtudiantSeeder::class,
            RequestTypeSeeder::class,    // types de documents par catégorie
            CandidatureDocsSeeder::class, // pièces de la candidature (procédure publique)
            UserSeeder::class,           // compte super-administrateur
            TestStudentSeeder::class,    // compte étudiant de test (ETUD001 / etudiant123)
        ]);
    }
}
