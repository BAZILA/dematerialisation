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

        Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        return back()->with('success', "Rôle « {$data['name']} » créé. Cochez ses permissions puis enregistrez.");
    }

    /** Met à jour les permissions d'un rôle. */
    public function update(Request $request, Role $role)
    {
        if ($role->name === 'super_admin') {
            return back()->withErrors('Le rôle super_admin possède toujours toutes les permissions.');
        }

        $perms = array_keys($request->input('perms', []));
        $valid = Permission::whereIn('name', $perms)->pluck('name')->toArray();
        $role->syncPermissions($valid);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
        $role->delete();

        return back()->with('success', 'Rôle supprimé.');
    }
}
