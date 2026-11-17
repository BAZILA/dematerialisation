<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /** Rôles réservés au système (non supprimables ; super_admin non éditable). */
    private array $systemRoles = ['super_admin', 'etudiant', 'candidat'];

    public function index()
    {
        $roles = Role::withCount('users')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('admin.roles.index', [
            'roles'       => $roles,
            'permissions' => $permissions,
            'systemRoles' => $this->systemRoles,
        ]);
    }

    /** Crée un rôle vide (permissions à cocher ensuite). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50|regex:/^[a-z0-9_]+$/|unique:roles,name',
        ], [
            'name.regex' => 'Le nom technique doit être en minuscules_avec_underscores.',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        \App\Models\AuditLog::record('role.cree', null, "Rôle créé : {$role->name}");

        return back()->with('success', "Rôle « {$data['name']} » créé. Cochez ses permissions puis enregistrez.");
    }

    /** Met à jour les permissions d'un rôle. */
    public function update(Request $request, Role $role)
    {
        if ($role->name === 'super_admin') {
            return back()->withErrors('Le rôle super_admin possède toujours toutes les permissions.');
        }

        /* Les rôles des usagers sont portés par TOUS les étudiants. Y
           cocher « acceder-backoffice » ouvrirait le back-office à des
           milliers de comptes d'un seul clic : réservé au super-admin. */
        if (in_array($role->name, ['etudiant', 'candidat'], true) && !$request->user()->isSuperAdmin()) {
            return back()->withErrors(
                'Les rôles des usagers (etudiant, candidat) ne sont modifiables que par un super-administrateur.');
        }

        $avant = $role->permissions->pluck('name')->sort()->values();

        $perms = array_keys($request->input('perms', []));
        $valid = Permission::whereIn('name', $perms)->pluck('name')->toArray();

        /* Nul ne met dans un rôle une permission qu'il n'a pas — sinon
           il suffirait d'enrichir son propre rôle pour tout obtenir.
           Seules les permissions AJOUTÉES sont contrôlées : on peut
           retirer, ou laisser en place, ce qu'on ne détient pas. */
        $interdites = \App\Support\Privileges::permissionsInterdites(
            $request->user(), array_diff($valid, $avant->all()));
        if ($interdites) {
            \App\Models\AuditLog::record('role.escalade_refusee', null,
                "Tentative d'ajout de permissions non détenues au rôle {$role->name} : " . implode(', ', $interdites),
                ['role' => $role->name, 'refusees' => array_values($interdites)]);

            return back()->withErrors(
                "Vous ne pouvez pas accorder des permissions que vous n'avez pas vous-même : "
                . implode(', ', $interdites) . '.');
        }

        $role->syncPermissions($valid);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Audit : quelles permissions ont été ajoutées / retirées
        $apres    = collect($valid)->sort()->values();
        $ajoutees = $apres->diff($avant)->values();
        $retirees = $avant->diff($apres)->values();
        if ($ajoutees->isNotEmpty() || $retirees->isNotEmpty()) {
            \App\Models\AuditLog::record('role.modifie', null,
                "Permissions du rôle {$role->name} modifiées"
                    . ($ajoutees->isNotEmpty() ? ' — ajout : ' . $ajoutees->implode(', ') : '')
                    . ($retirees->isNotEmpty() ? ' — retrait : ' . $retirees->implode(', ') : ''),
                ['role' => $role->name, 'ajoutees' => $ajoutees->all(), 'retirees' => $retirees->all()]);
        }

        return back()->with('success', "Permissions du rôle « {$role->name} » mises à jour.");
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, $this->systemRoles, true)) {
            return back()->withErrors('Ce rôle système ne peut pas être supprimé.');
        }
        if ($role->users()->count() > 0) {
            return back()->withErrors("Impossible : {$role->users()->count()} utilisateur(s) portent encore ce rôle.");
        }
        \App\Models\AuditLog::record('role.supprime', null, "Rôle supprimé : {$role->name}");

        $role->delete();

        return back()->with('success', 'Rôle supprimé.');
    }
}
