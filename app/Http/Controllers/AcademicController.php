<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Parcours;
use Illuminate\Http\Request;

class AcademicController extends Controller
{
    public function index()
    {
        $facultes = Faculte::with(['departements.parcours'])->orderBy('nom')->get();

        return view('admin.academic.index', compact('facultes'));
    }

    /* ── Facultés / Instituts ─────────────────────────────── */

    public function storeFaculte(Request $request)
    {
        $data = $request->validate([
            'nom'   => 'required|string|max:200',
            'sigle' => 'nullable|string|max:30',
            'type'  => 'required|in:faculte,institut',
        ]);
        Faculte::create($data + ['is_active' => true]);

        return back()->with('success', 'Composante ajoutée.');
    }

    public function updateFaculte(Request $request, Faculte $faculte)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:200',
            'sigle'     => 'nullable|string|max:30',
            'type'      => 'required|in:faculte,institut',
            'is_active' => 'nullable|boolean',
        ]);
        $faculte->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Composante mise à jour.');
    }

    public function destroyFaculte(Faculte $faculte)
    {
        $faculte->delete();

        return back()->with('success', 'Composante supprimée.');
    }

    /* ── Départements ─────────────────────────────────────── */

    public function storeDepartement(Request $request)
    {
        $data = $request->validate([
            'faculte_id' => 'required|exists:facultes,id',
            'nom'        => 'required|string|max:200',
            'sigle'      => 'nullable|string|max:30',
        ]);
        Departement::create($data + ['is_active' => true]);

        return back()->with('success', 'Département ajouté.');
    }

    public function updateDepartement(Request $request, Departement $departement)
    {
        $data = $request->validate([
            'faculte_id' => 'required|exists:facultes,id',
            'nom'        => 'required|string|max:200',
            'sigle'      => 'nullable|string|max:30',
            'is_active'  => 'nullable|boolean',
        ]);
        $departement->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Département mis à jour.');
    }

    public function destroyDepartement(Departement $departement)
    {
        $departement->delete();

        return back()->with('success', 'Département supprimé.');
    }

    /* ── Parcours ─────────────────────────────────────────── */

    public function storeParcours(Request $request)
    {
        $data = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'nom'            => 'required|string|max:200',
            'sigle'          => 'nullable|string|max:30',
            'grade'          => 'required|in:licence,master,doctorat,specialisation,certificat',
        ]);
        Parcours::create($data + ['is_active' => true]);

        return back()->with('success', 'Parcours ajouté.');
    }

    public function updateParcours(Request $request, Parcours $parcours)
    {
        $data = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'nom'            => 'required|string|max:200',
            'sigle'          => 'nullable|string|max:30',
            'grade'          => 'required|in:licence,master,doctorat,specialisation,certificat',
            'is_active'      => 'nullable|boolean',
        ]);
        $parcours->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Parcours mis à jour.');
    }

    public function destroyParcours(Parcours $parcours)
    {
        $parcours->delete();

        return back()->with('success', 'Parcours supprimé.');
    }
}
