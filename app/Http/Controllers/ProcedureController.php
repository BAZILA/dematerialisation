<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProcedureController extends Controller
{
    public function index()
    {
        $procedures = Procedure::orderBy('ordre')->orderBy('id')
            ->withCount('requestTypes')
            ->get();

        return view('admin.procedures.index', compact('procedures'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'          => 'required|alpha_dash|max:50|unique:procedures,code',
            'nom'           => 'required|string|max:150',
            'description'   => 'nullable|string|max:500',
            'ordre'         => 'nullable|integer|min:0',
        ]);

        $code = Str::of($data['code'])->lower()->replace('-', '_')->toString();

        Procedure::create([
            'code'          => $code,
            'nom'           => $data['nom'],
            'description'   => $data['description'] ?? null,
            'requires_auth' => $request->boolean('requires_auth'),
            'pour_nouveaux' => $request->boolean('pour_nouveaux'),
            'pour_anciens'  => $request->boolean('pour_anciens'),
            'is_active'     => true,
            'ordre'         => $data['ordre'] ?? 99,
        ]);

        // Permission de traitement associée (assignable aux rôles depuis l'UI)
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => $code . '.traiter', 'guard_name' => 'web']
        );
        // Les rôles « couvre-tout » la reçoivent d'office
        foreach (['chef_scolarite'] as $roleName) {
            if ($role = \Spatie\Permission\Models\Role::where('name', $roleName)->first()) {
                $role->givePermissionTo($perm);
            }
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Procédure « {$data['nom']} » créée. Ajoutez-lui ses types de documents.");
    }

    public function update(Request $request, Procedure $procedure)
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'ordre'       => 'nullable|integer|min:0',
        ]);

        $procedure->update([
            'nom'           => $data['nom'],
            'description'   => $data['description'] ?? null,
            'requires_auth' => $request->boolean('requires_auth'),
            'pour_nouveaux' => $request->boolean('pour_nouveaux'),
            'pour_anciens'  => $request->boolean('pour_anciens'),
            'is_active'     => $request->boolean('is_active'),
            'ordre'         => $data['ordre'] ?? $procedure->ordre,
        ]);

        return back()->with('success', 'Procédure mise à jour.');
    }

    public function destroy(Procedure $procedure)
    {
        if (RequestType::where('category', $procedure->code)->exists()) {
            return back()->withErrors(
                "Impossible de supprimer « {$procedure->nom} » : des types de documents y sont rattachés. Désactivez-la plutôt."
            );
        }

        $procedure->delete();

        return back()->with('success', 'Procédure supprimée.');
    }
}
