<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Compte administrateur
        User::firstOrCreate(
            ['numero_identifiant' => 'ADMIN001'],
            [
                'email'         => 'admin@admission.com',
                'password'      => Hash::make('admin123456'),
                'role'          => 'admin',
                'type_etudiant' => null,
                'is_active'     => true,
            ]
        );

        /*
         * NOTE : Les comptes étudiants ne sont plus créés manuellement.
         * Ils sont générés automatiquement lors de l'inscription (AuthController::completeRegistration).
         * Les bacheliers et anciens étudiants sont dans BachelierSeeder / AncienEtudiantSeeder.
         *
         * Identifiants de test admin :
         *   ADMIN001 / admin123456
         *
         * Données de test bacheliers disponibles :
         *   Nom: MENSAH    | Prénom: Kofi  | Naissance: 15/03/2004 | Lieu: Lomé    | Sexe: M
         *   Nom: AZIATO    | Prénom: Ama   | Naissance: 22/07/2004 | Lieu: Kpalimé | Sexe: F
         *
         * Données de test anciens étudiants :
         *   Carte: CARTE0000001 | Nom: AGBEKO | Prénom: Yves | Naissance: 14/05/2001 | Sexe: M
         *   Carte: CARTE0000002 | Nom: TETTEH | Prénom: Akua | Naissance: 30/01/2002 | Sexe: F
         */
    }
}
