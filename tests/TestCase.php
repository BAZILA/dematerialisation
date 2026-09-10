<?php

namespace Tests;

use App\Models\AnneeAcademique;
use App\Models\Candidature;
use App\Models\CandidatureChoix;
use App\Models\Parcours;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    /**
     * AnneeAcademique memoise l'annee active dans une propriete statique,
     * valable le temps d'une requete HTTP. Les tests partagent un seul
     * processus : sans remise a zero, l'annee d'un test survit au suivant
     * et fausse silencieusement toutes les fenetres d'ouverture.
     */
    protected function setUp(): void
    {
        parent::setUp();
        AnneeAcademique::flushMemo();
    }

    /**
     * Référentiel minimal : rôles, permissions et réglages.
     * Les tests qui touchent aux droits ou aux modules en ont besoin.
     */
    protected function socle(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\SettingSeeder::class);
        $this->seed(\Database\Seeders\ProcedureSeeder::class);
    }

    /** Un compte du personnel, avec les permissions demandées. */
    protected function agent(array $permissions = [], string $identifiant = 'AGENT001'): User
    {
        $user = User::create([
            'numero_identifiant' => $identifiant,
            'nom'       => 'AGENT',
            'prenom'    => 'Test',
            'email'     => strtolower($identifiant) . '@test.local',
            'password'  => Hash::make('secret1234'),
            'role'      => 'agent_candidature',
            'is_active' => true,
        ]);

        foreach ($permissions as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $user->syncPermissions($permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** Un super-administrateur, qui passe partout. */
    protected function superAdmin(): User
    {
        $user = User::create([
            'numero_identifiant' => 'SUPER001',
            'nom'       => 'SUPER',
            'prenom'    => 'Admin',
            'email'     => 'super@test.local',
            'password'  => Hash::make('secret1234'),
            'role'      => 'super_admin',
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** Un parcours rattaché à une faculté et un département. */
    protected function parcours(string $nom = 'Informatique'): Parcours
    {
        $faculte = \App\Models\Faculte::create([
            'nom' => 'Faculté des Sciences', 'sigle' => 'FDS',
            'type' => 'faculte', 'is_active' => true,
        ]);
        $departement = \App\Models\Departement::create([
            'faculte_id' => $faculte->id, 'nom' => 'Département ' . $nom,
            'sigle' => 'D' . substr($nom, 0, 3), 'is_active' => true,
        ]);

        return Parcours::create([
            'departement_id' => $departement->id,
            'nom' => $nom, 'sigle' => strtoupper(substr($nom, 0, 4)),
            'grade' => 'licence', 'is_active' => true,
        ]);
    }

    /**
     * Un type de document attendu. Sans parcours = pièce COMMUNE ;
     * avec parcours = pièce PROPRE à ce parcours.
     */
    protected function typeDocument(string $libelle = 'Acte de naissance', ?Parcours $parcours = null): \App\Models\RequestType
    {
        $type = \App\Models\RequestType::create([
            'label'       => $libelle,
            'category'    => 'candidature',
            'is_required' => true,
            'is_active'   => true,
        ]);

        if ($parcours) {
            $type->parcours()->attach($parcours->id);
        }

        return $type->fresh(['parcours']);
    }

    /** Une candidature avec un vœu sur le parcours donné. */
    protected function candidature(Parcours $parcours, string $nom = 'DUPONT'): Candidature
    {
        $annee = AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true]
        );

        $candidature = Candidature::create([
            'reference' => 'CAND-TEST-' . strtoupper(\Illuminate\Support\Str::random(6)),
            'nom' => $nom, 'prenom' => 'Jean', 'sexe' => 'M',
            'date_naissance' => '2004-05-12', 'lieu_naissance' => 'Kara',
            'email' => strtolower($nom) . '@test.local', 'telephone' => '90000000',
            'annee_academique_id' => $annee->id,
            'status' => 'soumise',
        ]);

        CandidatureChoix::create([
            'candidature_id' => $candidature->id,
            'parcours_id'    => $parcours->id,
            'ordre'          => 1,
            'statut'         => 'soumise',
        ]);

        return $candidature->fresh(['choix']);
    }
}
