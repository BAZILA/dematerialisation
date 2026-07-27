<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Rôles assignables au personnel (hors usagers). */
    private function staffRoles()
    {
        return Role::whereNotIn('name', ['etudiant', 'candidat'])->orderBy('name')->get();
    }

    public function index()
    {
        $users = User::whereNotIn('role', ['etudiant', 'candidat'])
            ->orderBy('nom')->orderBy('numero_identifiant')
            ->paginate(\App\Models\Setting::perPage())->withQueryString();
        $roles = $this->staffRoles();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_identifiant' => 'required|string|max:50|unique:users,numero_identifiant',
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'role'      => 'required|exists:roles,name',
            'password'  => 'required|string|min:8',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);
        $this->assertStaffRole($data['role']);

        $user = User::create([
            'numero_identifiant' => $data['numero_identifiant'],
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);
        $user->syncRoles([$data['role']]);

        if ($request->hasFile('signature')) {
            $user->update(['signature_path' => $request->file('signature')->store('signatures', 'local')]);
        }

        \App\Models\AuditLog::record('utilisateur.cree', $user,
            "Compte créé : {$user->staff_name} ({$user->numero_identifiant}) — rôle {$data['role']}",
            ['role' => $data['role']]);

        return back()->with('success', "Compte « {$user->staff_name} » créé.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'role'      => 'required|exists:roles,name',
            'password'  => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);
        $this->assertStaffRole($data['role']);

        $update = [
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                Storage::disk('local')->delete($user->signature_path);
            }
            $update['signature_path'] = $request->file('signature')->store('signatures', 'local');
        }
        $ancienRole = $user->role;
        $user->update($update);
        $user->syncRoles([$data['role']]);

        \App\Models\AuditLog::record('utilisateur.modifie', $user,
            "Compte modifié : {$user->staff_name} ({$user->numero_identifiant})"
                . ($ancienRole !== $data['role'] ? " — rôle {$ancienRole} → {$data['role']}" : ''),
            [
                'role' => $data['role'],
                'mot_de_passe_change' => !empty($data['password']),
                'desactive' => !$request->boolean('is_active'),
            ]);

        return back()->with('success', 'Compte mis à jour.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
        }

        \App\Models\AuditLog::record('utilisateur.supprime', null,
            "Compte supprimé : {$user->staff_name} ({$user->numero_identifiant}) — rôle {$user->role}",
            ['role' => $user->role, 'user_id_supprime' => $user->id]);

        $user->delete();

        return back()->with('success', 'Compte supprimé.');
    }

    /** Sert l'image de signature (accès protégé par la permission de route). */
    public function signature(User $user)
    {
        abort_unless(
            $user->signature_path && Storage::disk('local')->exists($user->signature_path),
            404
        );

        return Storage::disk('local')->response($user->signature_path);
    }

    private function assertStaffRole(string $role): void
    {
        abort_if(in_array($role, ['etudiant', 'candidat'], true), 422, 'Rôle non assignable au personnel.');
    }
}
