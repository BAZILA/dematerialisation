<?php

namespace Database\Seeders;

use App\Models\VerifiedStudent;
use App\Models\VerifiedBachelier;
use Illuminate\Database\Seeder;

class VerifiedDataSeeder extends Seeder
{
    public function run(): void
    {
        // ===== ÉTUDIANTS VÉRIFIÉS (Anciens) =====
        VerifiedStudent::create([
            'numero_carte' => 'CARTE0000001',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'sexe' => 'M',
            'date_naissance' => '1998-05-15',
            'lieu_naissance' => 'Paris',
            'email' => 'jean.dupont@student.com',
            'telephone' => '0601020304',
            'adresse' => '123 Rue de la Paix, 75000 Paris',
            'is_registered' => false,
        ]);

        VerifiedStudent::create([
            'numero_carte' => 'CARTE0000002',
            'nom' => 'Martin',
            'prenom' => 'Sophie',
            'sexe' => 'F',
            'date_naissance' => '1999-08-22',
            'lieu_naissance' => 'Lyon',
            'email' => 'sophie.martin@student.com',
            'telephone' => '0605060708',
            'adresse' => '456 Avenue des Champs, 69000 Lyon',
            'is_registered' => false,
        ]);

        VerifiedStudent::create([
            'numero_carte' => 'CARTE0000003',
            'nom' => 'Bernard',
            'prenom' => 'Pierre',
            'sexe' => 'M',
            'date_naissance' => '1997-11-30',
            'lieu_naissance' => 'Marseille',
            'email' => 'pierre.bernard@student.com',
            'telephone' => '0609101112',
            'adresse' => '789 Boulevard Maritime, 13000 Marseille',
            'is_registered' => false,
        ]);

        VerifiedStudent::create([
            'numero_carte' => 'CARTE0000004',
            'nom' => 'Laurent',
            'prenom' => 'Marie',
            'sexe' => 'F',
            'date_naissance' => '2000-01-10',
            'lieu_naissance' => 'Toulouse',
            'email' => 'marie.laurent@student.com',
            'telephone' => '0611121314',
            'adresse' => '321 Rue de la Republique, 31000 Toulouse',
            'is_registered' => false,
        ]);

        VerifiedStudent::create([
            'numero_carte' => 'CARTE0000005',
            'nom' => 'Petit',
            'prenom' => 'Luc',
            'sexe' => 'M',
            'date_naissance' => '1998-07-20',
            'lieu_naissance' => 'Bordeaux',
            'email' => 'luc.petit@student.com',
            'telephone' => '0613141516',
            'adresse' => '654 Quai de la Garonne, 33000 Bordeaux',
            'is_registered' => false,
        ]);

        // ===== NOUVEAUX BACHELIERS =====
        VerifiedBachelier::create([
            'numero_table' => 'TABLE00001',
            'nom' => 'Moreau',
            'prenom' => 'Alice',
            'sexe' => 'F',
            'date_naissance' => '2006-03-14',
            'email' => 'alice.moreau@bachelier.com',
            'telephone' => '0717181920',
            'baccalaureat_serie' => 'S',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        VerifiedBachelier::create([
            'numero_table' => 'TABLE00002',
            'nom' => 'Garcia',
            'prenom' => 'Carlos',
            'sexe' => 'M',
            'date_naissance' => '2006-06-22',
            'email' => 'carlos.garcia@bachelier.com',
            'telephone' => '0721222324',
            'baccalaureat_serie' => 'ES',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        VerifiedBachelier::create([
            'numero_table' => 'TABLE00003',
            'nom' => 'Rousseau',
            'prenom' => 'Julien',
            'sexe' => 'M',
            'date_naissance' => '2006-09-05',
            'email' => 'julien.rousseau@bachelier.com',
            'telephone' => '0723242526',
            'baccalaureat_serie' => 'STI2D',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        VerifiedBachelier::create([
            'numero_table' => 'TABLE00004',
            'nom' => 'Lefevre',
            'prenom' => 'Emma',
            'sexe' => 'F',
            'date_naissance' => '2006-12-11',
            'email' => 'emma.lefevre@bachelier.com',
            'telephone' => '0725262728',
            'baccalaureat_serie' => 'L',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        VerifiedBachelier::create([
            'numero_table' => 'TABLE00005',
            'nom' => 'Dubois',
            'prenom' => 'Thomas',
            'sexe' => 'M',
            'date_naissance' => '2006-02-28',
            'email' => 'thomas.dubois@bachelier.com',
            'telephone' => '0727282930',
            'baccalaureat_serie' => 'S',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        VerifiedBachelier::create([
            'numero_table' => 'TABLE00006',
            'nom' => 'Leclerc',
            'prenom' => 'Nathalie',
            'sexe' => 'F',
            'date_naissance' => '2006-04-16',
            'email' => 'nathalie.leclerc@bachelier.com',
            'telephone' => '0729303132',
            'baccalaureat_serie' => 'S',
            'baccalaureat_annee' => 2024,
            'is_registered' => false,
        ]);

        // ===== FICHIERS REQUIS POUR ANCIENS ÉTUDIANTS =====
        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'ancien',
            'code_fichier' => 'ancien_fiche_inscription',
            'nom_affiche' => 'Fiche d\'Inscription',
            'description' => 'Votre fiche d\'inscription officielle',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 1,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'ancien',
            'code_fichier' => 'ancien_fiche_ue',
            'nom_affiche' => 'Fiche d\'UE',
            'description' => 'Fiche des unités d\'enseignement complétées',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 2,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'ancien',
            'code_fichier' => 'ancien_fiche_paiement',
            'nom_affiche' => 'Fiche de Paiement',
            'description' => 'Preuve du paiement des frais de scolarité',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 3,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'ancien',
            'code_fichier' => 'ancien_nationalite',
            'nom_affiche' => 'Nationalité',
            'description' => 'Justificatif de nationalité',
            'obligatoire' => true,
            'est_officiel' => false,
            'ordre_affichage' => 4,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'ancien',
            'code_fichier' => 'ancien_diplome',
            'nom_affiche' => 'Diplôme',
            'description' => 'Copie certifiée du diplôme',
            'obligatoire' => false,
            'est_officiel' => false,
            'ordre_affichage' => 5,
        ]);

        // ===== FICHIERS REQUIS POUR NOUVEAUX BACHELIERS =====
        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'nouveau',
            'code_fichier' => 'nouveau_fiche_inscription',
            'nom_affiche' => 'Fiche d\'Inscription',
            'description' => 'Votre fiche d\'inscription officielle',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 1,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'nouveau',
            'code_fichier' => 'nouveau_fiche_ue',
            'nom_affiche' => 'Fiche d\'UE',
            'description' => 'Fiche des unités d\'enseignement du baccalauréat',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 2,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'nouveau',
            'code_fichier' => 'nouveau_fiche_paiement',
            'nom_affiche' => 'Fiche de Paiement',
            'description' => 'Preuve du paiement des droits d\'inscription',
            'obligatoire' => true,
            'est_officiel' => true,
            'ordre_affichage' => 3,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'nouveau',
            'code_fichier' => 'nouveau_nationalite',
            'nom_affiche' => 'Nationalité',
            'description' => 'Justificatif de nationalité',
            'obligatoire' => true,
            'est_officiel' => false,
            'ordre_affichage' => 4,
        ]);

        \App\Models\SubmissionRequiredFile::create([
            'type_profil' => 'nouveau',
            'code_fichier' => 'nouveau_baccalaureat',
            'nom_affiche' => 'Baccalauréat',
            'description' => 'Diplôme du baccalauréat',
            'obligatoire' => true,
            'est_officiel' => false,
            'ordre_affichage' => 5,
        ]);

        echo "\n✅ Données de test créées avec succès!\n";
        echo "📚 Étudiants vérifiés: 5\n";
        echo "📚 Bacheliers vérifiés: 6\n";
        echo "📋 Fichiers requis: 10\n";
    }
}
