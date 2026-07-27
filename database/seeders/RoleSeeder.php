<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /* ── Permissions ─────────────────────────────────────── */
        $permissions = [
            'acceder-backoffice',
            // Traitement des demandes par service
            'depot.traiter', 'attestation.traiter', 'releve.traiter',
            'diplome.traiter', 'duplicat.traiter', 'homologation.traiter',
            'candidature.traiter',
            // Transverses
            'demandes.controler', 'documents.valider', 'documents.signer',
            'paiements.confirmer', 'dossiers.consulter',
            // Annuler une validation / rouvrir un dossier (PAS les simples agents)
            'demandes.rouvrir',
            // Administration / configuration — permissions « globales » (tout le module)
            'identites.importer', 'academique.gerer', 'annees.gerer',
            'parametres.gerer', 'utilisateurs.gerer', 'roles.gerer',
            'perimetres.affecter', 'audit.consulter',
            // Archives numériques
            'archives.consulter',
            // Statistiques (réservées : direction / supervision)
            'statistiques.consulter',
            // ── Permissions FINES par action (voir / créer / éditer / supprimer) ──
            // Chaque route accepte la fine OU la globale du module.
            'academique.voir', 'academique.creer', 'academique.editer', 'academique.supprimer',
            'annees.voir', 'annees.creer', 'annees.editer', 'annees.supprimer',
            'utilisateurs.voir', 'utilisateurs.creer', 'utilisateurs.editer', 'utilisateurs.supprimer',
            'roles.voir', 'roles.creer', 'roles.editer', 'roles.supprimer',
            'procedures.voir', 'procedures.creer', 'procedures.editer', 'procedures.supprimer',
            'types.voir', 'types.creer', 'types.editer', 'types.supprimer',
            'perimetres.voir', 'identites.voir',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $tousTraitements = [
            'depot.traiter', 'attestation.traiter', 'releve.traiter', 'diplome.traiter',
            'duplicat.traiter', 'homologation.traiter', 'candidature.traiter',
        ];

        /* ── Rôles → permissions ─────────────────────────────── */
        $roles = [
            'super_admin'             => Permission::all()->pluck('name')->toArray(),
            'admin'                   => [
                'acceder-backoffice', 'academique.gerer', 'annees.gerer', 'identites.importer',
                'utilisateurs.gerer', 'perimetres.affecter', 'dossiers.consulter', 'audit.consulter',
                'archives.consulter', 'statistiques.consulter', 'demandes.rouvrir',
            ],
            // Archiviste : consultation et export de l'archive des dossiers validés
            'archiviste'              => [
                'acceder-backoffice', 'archives.consulter', 'dossiers.consulter',
            ],
            'gestionnaire_academique' => [
                'acceder-backoffice', 'academique.gerer', 'annees.gerer', 'dossiers.consulter',
            ],
            'chef_scolarite'          => array_merge(
                ['acceder-backoffice', 'demandes.controler', 'dossiers.consulter', 'statistiques.consulter', 'demandes.rouvrir'],
                $tousTraitements
            ),
            'directeur_daas'          => [
                'acceder-backoffice', 'documents.valider', 'documents.signer',
                'dossiers.consulter', 'audit.consulter', 'statistiques.consulter',
            ],
            'directeur_adjoint_daas'  => [
                'acceder-backoffice', 'documents.valider', 'documents.signer',
                'dossiers.consulter', 'audit.consulter', 'statistiques.consulter',
            ],
            'agent_depot'             => ['acceder-backoffice', 'depot.traiter', 'dossiers.consulter'],
            'agent_carte'             => ['acceder-backoffice', 'duplicat.traiter', 'dossiers.consulter'],
            'agent_attestation'       => ['acceder-backoffice', 'attestation.traiter', 'dossiers.consulter'],
            'agent_releve'            => ['acceder-backoffice', 'releve.traiter', 'dossiers.consulter'],
            'agent_homologation'      => ['acceder-backoffice', 'homologation.traiter', 'dossiers.consulter'],
            'agent_diplome'           => ['acceder-backoffice', 'diplome.traiter', 'dossiers.consulter'],
            'agent_candidature'       => ['acceder-backoffice', 'candidature.traiter', 'dossiers.consulter'],
            'agent_financier'         => ['acceder-backoffice', 'paiements.confirmer', 'dossiers.consulter'],
            'auditeur'                => ['acceder-backoffice', 'audit.consulter', 'dossiers.consulter'],
            // Usagers (aucune permission back-office)
            'etudiant'                => [],
            'candidat'                => [],
        ];

        foreach ($roles as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
