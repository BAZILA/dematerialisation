<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use App\Models\Domaine;
use App\Models\Faculte;
use App\Models\Parcours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicController extends Controller
{
    public function index()
    {
        $facultes = Faculte::with(['departements.parcours.domaine'])->orderBy('nom')->get();
        $domaines = Domaine::orderBy('nom')->get();

        return view('admin.academic.index', compact('facultes', 'domaines'));
    }

    /* ── Composantes : facultés / instituts / écoles ──────── */

    public function storeFaculte(Request $request)
    {
        $data = $request->validate([
            'nom'   => 'required|string|max:200',
            'sigle' => 'nullable|string|max:30',
            'type'  => ['required', Rule::in(array_keys(Faculte::$types))],
        ]);
        Faculte::create($data + ['is_active' => true]);

        return back()->with('success', 'Composante ajoutée.');
    }

    public function updateFaculte(Request $request, Faculte $faculte)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:200',
            'sigle'     => 'nullable|string|max:30',
            'type'      => ['required', Rule::in(array_keys(Faculte::$types))],
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
            'domaine_id'     => 'nullable|exists:domaines,id',
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
            'domaine_id'     => 'nullable|exists:domaines,id',
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

    /* ── Domaines (référentiel transverse, sert au verso des dépôts) ── */

    public function storeDomaine(Request $request)
    {
        $data = $request->validate([
            'nom'   => 'required|string|max:200',
            'sigle' => 'nullable|string|max:30',
        ]);
        Domaine::create($data + ['is_active' => true]);

        return back()->with('success', 'Domaine ajouté.');
    }

    public function updateDomaine(Request $request, Domaine $domaine)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:200',
            'sigle'     => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
        ]);
        $domaine->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Domaine mis à jour.');
    }

    public function destroyDomaine(Domaine $domaine)
    {
        $domaine->delete();

        return back()->with('success', 'Domaine supprimé.');
    }
}
