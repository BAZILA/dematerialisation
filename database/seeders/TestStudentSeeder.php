<?php

namespace Database\Seeders;

use App\Models\Bachelier;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Compte étudiant prêt à l'emploi pour les tests.
 * Identifiant : ETUD001   —   Mot de passe : etudiant123
 */
class TestStudentSeeder extends Seeder
{
    public function run(): void
    {
        $b = Bachelier::first();
        if (!$b) {
            return;
        }

        $user = User::firstOrCreate(
            ['numero_identifiant' => 'ETUD001'],
            [
                'email'         => 'etudiant.test@uk.local',
                'password'      => Hash::make('etudiant123'),
                'role'          => 'etudiant',
                'type_etudiant' => 'nouveau',
                'is_active'     => true,
            ]
        );
        $user->syncRoles(['etudiant']);

        Student::firstOrCreate(
            ['user_id' => $user->id],
            [
                'bachelier_id'   => $b->id,
                'nom'            => $b->nom,
                'prenom'         => $b->prenom,
                'sexe'           => $b->sexe,
                'date_naissance' => $b->date_naissance,
                'lieu_naissance' => $b->lieu_naissance,
            ]
        );

        $b->update(['is_registered' => true]);
    }
}
