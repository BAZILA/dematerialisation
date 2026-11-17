<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /* Compte super-administrateur d'INSTALLATION.

           Ce seeder posait le mot de passe « admin123456 », écrit ici en
           clair — donc lisible par quiconque a vu le dépôt, qui a été
           public. Relancé sur une base en service, il recréait ce compte
           avec ce mot de passe connu de tous.

           Désormais : hors environnement local, il ne fait RIEN si un
           super-administrateur existe déjà ; et s'il doit créer le compte,
           le mot de passe est tiré au hasard, affiché une seule fois, et
           devra être changé à la première connexion. */
        $existe = User::role('super_admin')->exists();

        if ($existe && !app()->environment('local', 'testing')) {
            $this->command?->warn('UserSeeder : un super-administrateur existe déjà — rien à faire.');

            return;
        }

        if (User::where('numero_identifiant', 'ADMIN001')->exists()) {
            return;
        }

        $local      = app()->environment('local', 'testing');
        $motDePasse = $local ? 'admin123456' : \Illuminate\Support\Str::password(16, true, true, false, false);

        $admin = User::create([
            'numero_identifiant' => 'ADMIN001',
            'email'         => 'admin@uk.local',
            'password'      => Hash::make($motDePasse),
            'role'          => 'super_admin',
            'type_etudiant' => null,
            'is_active'     => true,
        ]);
        $admin->syncRoles(['super_admin']);

        if (!$local) {
            $admin->forceFill(['doit_changer_mot_de_passe' => true])->save();

            $this->command?->warn('');
            $this->command?->warn('  Compte super-administrateur créé :');
            $this->command?->warn('    identifiant  : ADMIN001');
            $this->command?->warn("    mot de passe : {$motDePasse}");
            $this->command?->warn('  Notez-le maintenant : il ne sera plus jamais affiché.');
            $this->command?->warn('  Il devra être changé à la première connexion.');
            $this->command?->warn('');
        }

        /*
         * NOTE : Les comptes étudiants ne sont plus créés manuellement.
         * Ils sont générés automatiquement lors de l'inscription (AuthController::completeRegistration).
         * Les bacheliers et anciens étudiants sont dans BachelierSeeder / AncienEtudiantSeeder.
         *
         * Identifiants de test admin (environnement LOCAL uniquement) :
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
