<?php

namespace Database\Seeders;

use App\Models\RequestType;
use Illuminate\Database\Seeder;

class RequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [

            /* ════════════════════════════════════════════
             | DÉPÔT DE DOSSIER
             | type_etudiant = 'nouveau' | 'ancien' | 'both'
             ════════════════════════════════════════════ */

            // ── Commun aux deux ──────────────────────────
            ['category'=>'depot','type_etudiant'=>'both',    'label'=>"Acte de naissance",             'is_required'=>true,  'has_stamp'=>false, 'order'=>1],
            ['category'=>'depot','type_etudiant'=>'both',    'label'=>"Certificat de nationalité",      'is_required'=>true,  'has_stamp'=>false, 'order'=>2],
            ['category'=>'depot','type_etudiant'=>'both',    'label'=>"Photo d'identité récente",       'is_required'=>true,  'has_stamp'=>false, 'order'=>3],
            ['category'=>'depot','type_etudiant'=>'both',    'label'=>"Fiche de paiement des frais",    'is_required'=>true,  'has_stamp'=>false, 'order'=>9],

            // ── Nouveau bachelier uniquement ─────────────
            ['category'=>'depot','type_etudiant'=>'nouveau', 'label'=>"Relevé de notes du Baccalauréat",'is_required'=>true,  'has_stamp'=>true,  'order'=>4,
                'description'=>"Original ou copie certifiée conforme"],
            ['category'=>'depot','type_etudiant'=>'nouveau', 'label'=>"Fiche d'inscription",            'is_required'=>true,  'has_stamp'=>true,  'order'=>5,
                'description'=>"Fiche remplie et signée"],
            ['category'=>'depot','type_etudiant'=>'nouveau', 'label'=>"Attestation de réussite au Bac", 'is_required'=>false, 'has_stamp'=>false, 'order'=>6],

            // ── Ancien étudiant uniquement ───────────────
            ['category'=>'depot','type_etudiant'=>'ancien',  'label'=>"Carte étudiant de l'année précédente", 'is_required'=>true,  'has_stamp'=>false, 'order'=>4],
            ['category'=>'depot','type_etudiant'=>'ancien',  'label'=>"Relevé de notes",                'is_required'=>true,  'has_stamp'=>true,  'order'=>5,
                'description'=>"Relevé officiel de la dernière année"],
            ['category'=>'depot','type_etudiant'=>'ancien',  'label'=>"Attestation de réussite / inscription",'is_required'=>true, 'has_stamp'=>true, 'order'=>6],
            ['category'=>'depot','type_etudiant'=>'ancien',  'label'=>"Diplôme de Licence (si passage M1)",'is_required'=>false,'has_stamp'=>false, 'order'=>7,
                'description'=>"Obligatoire pour les étudiants entrant en Master 1"],

            /* ════════════════════════════════════════════
             | DEMANDE D'ATTESTATION — ancien uniquement
             ════════════════════════════════════════════ */
            ['category'=>'attestation','type_etudiant'=>'ancien','label'=>"Lettre de demande manuscrite",   'is_required'=>true,  'has_stamp'=>false, 'order'=>1],
            ['category'=>'attestation','type_etudiant'=>'ancien','label'=>"Carte étudiant ou reçu d'inscription",'is_required'=>true,'has_stamp'=>false,'order'=>2],
            ['category'=>'attestation','type_etudiant'=>'ancien','label'=>"Photo d'identité récente",       'is_required'=>true,  'has_stamp'=>false, 'order'=>3],

            /* ════════════════════════════════════════════
             | DEMANDE DE DIPLÔME — ancien uniquement
             ════════════════════════════════════════════ */
            ['category'=>'diplome','type_etudiant'=>'ancien','label'=>"Lettre de demande de diplôme",    'is_required'=>true,  'has_stamp'=>false, 'order'=>1],
            ['category'=>'diplome','type_etudiant'=>'ancien','label'=>"Relevé de notes final",           'is_required'=>true,  'has_stamp'=>false, 'order'=>2],
            ['category'=>'diplome','type_etudiant'=>'ancien','label'=>"Copie de la carte nationale d'identité",'is_required'=>true,'has_stamp'=>false,'order'=>3],
            ['category'=>'diplome','type_etudiant'=>'ancien','label'=>"Reçu de paiement des frais de diplôme",'is_required'=>true,'has_stamp'=>false,'order'=>4],
            ['category'=>'diplome','type_etudiant'=>'ancien','label'=>"Photo d'identité récente (x2)",   'is_required'=>true,  'has_stamp'=>false, 'order'=>5],

            /* ════════════════════════════════════════════
             | DEMANDE DE RELEVÉ DE NOTES — ancien uniquement
             ════════════════════════════════════════════ */
            ['category'=>'releve','type_etudiant'=>'ancien','label'=>"Lettre de demande de relevé",      'is_required'=>true,  'has_stamp'=>false, 'order'=>1],
            ['category'=>'releve','type_etudiant'=>'ancien','label'=>"Carte étudiant ou reçu",           'is_required'=>true,  'has_stamp'=>false, 'order'=>2],
            ['category'=>'releve','type_etudiant'=>'ancien','label'=>"Reçu de paiement des frais",       'is_required'=>false, 'has_stamp'=>false, 'order'=>3],

            /* ════════════════════════════════════════════
             | DUPLICATA DE CARTE ÉTUDIANT — ancien uniquement
             ════════════════════════════════════════════ */
            ['category'=>'duplicat','type_etudiant'=>'ancien','label'=>"Déclaration de perte (police ou gendarmerie)",'is_required'=>true,'has_stamp'=>false,'order'=>1],
            ['category'=>'duplicat','type_etudiant'=>'ancien','label'=>"Photo d'identité récente",       'is_required'=>true,  'has_stamp'=>false, 'order'=>2],
            ['category'=>'duplicat','type_etudiant'=>'ancien','label'=>"Copie de la carte nationale d'identité",'is_required'=>true,'has_stamp'=>false,'order'=>3],
            ['category'=>'duplicat','type_etudiant'=>'ancien','label'=>"Reçu de paiement des frais de duplicata",'is_required'=>true,'has_stamp'=>false,'order'=>4],
        ];

        /* Chaque piece appartient a un niveau. Les niveaux partagent les
           procedures, jamais les pieces : ce jeu de depart s'installe en
           licence, qui porte l'essentiel des effectifs. Les autres niveaux
           se montent depuis l'interface, ou par recopie. */
        foreach ($types as $t) {
            RequestType::firstOrCreate(
                [
                    'label'         => $t['label'],
                    'category'      => $t['category'],
                    'grade'         => 'licence',
                    'type_etudiant' => $t['type_etudiant'],
                ],
                array_merge($t, [
                    'grade'       => 'licence',
                    'is_active'   => true,
                    'description' => $t['description'] ?? null,
                ])
            );
        }
    }
}
