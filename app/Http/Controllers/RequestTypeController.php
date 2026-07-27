<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestTypeController extends Controller
{
    /* ── Helper redirect avec tab ──────────────────────── */
    private function backToTab(string $tab = null): \Illuminate\Http\RedirectResponse
    {
        $tab = $tab ?? request('tab', 'depot');
        return redirect()->route('admin.request-types.index', ['tab' => $tab]);
    }

    /* ── Index ─────────────────────────────────────────── */

    public function index()
    {
        $types = RequestType::with('parcours')
            ->orderBy('category')->orderBy('order')->orderBy('id')
            ->get()->groupBy('category');
        $categories = RequestType::categories();

        // Pour la portée des pièces de candidature (commune / propre à un parcours)
        $parcoursParFaculte = \App\Models\Parcours::with('departement.faculte')
            ->where('is_active', true)->orderBy('nom')->get()
            ->groupBy(fn ($p) => $p->departement?->faculte?->nom ?? 'Sans rattachement');

        return view('admin.request_types.index', compact('types', 'categories', 'parcoursParFaculte'));
    }

    /** Portée : aucun parcours coché (ou « commune ») = pièce commune à tous. */
    private function syncPortee(Request $request, RequestType $type): void
    {
        if ($type->category !== 'candidature') {
            $type->parcours()->detach();

            return;
        }
        $ids = $request->input('portee') === 'specifique'
            ? array_filter((array) $request->input('parcours_ids', []))
            : [];
        $type->parcours()->sync($ids);
    }

    /* ── Store ─────────────────────────────────────────── */

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:200',
            'category' => ['required', Rule::exists('procedures', 'code')],
            'type_etudiant' => 'required|in:nouveau,ancien,both',
            'description' => 'nullable|string|max:500',
            'is_required' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $type = RequestType::create([
            'label' => $data['label'],
            'category' => $data['category'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required', true),
            'has_stamp' => $request->boolean('has_stamp', false),
            'fills_verso' => $request->boolean('fills_verso', false),
            'is_active' => true,
            'order' => $data['order'] ?? 99,
        ]);

        $this->syncPortee($request, $type);

        return $this->backToTab($data['category'])
            ->with('success', "« {$data['label']} » ajouté avec succès.");
    }

    /* ── Update ─────────────────────────────────────────── */

    public function update(Request $request, RequestType $requestType)
    {
        $data = $request->validate([
            'label' => 'required|string|max:200',
            'category' => ['required', Rule::exists('procedures', 'code')],
            'type_etudiant' => 'required|in:nouveau,ancien,both',
            'description' => 'nullable|string|max:500',
            'is_required' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $requestType->update([
            'label' => $data['label'],
            'category' => $data['category'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required', true),
            'has_stamp' => $request->boolean('has_stamp', false),
            'fills_verso' => $request->boolean('fills_verso', false),
            'is_active' => $request->boolean('is_active', true),
            'order' => $data['order'] ?? $requestType->order,
        ]);

        $this->syncPortee($request, $requestType->refresh());

        return $this->backToTab($requestType->category)
            ->with('success', "« {$requestType->label} » mis à jour.");
    }

    /* ── Destroy ────────────────────────────────────────── */

    public function destroy(RequestType $requestType)
    {
        $cat = $requestType->category;

        if ($requestType->requestFiles()->count() > 0) {
            return $this->backToTab($cat)->withErrors(
                "Impossible de supprimer « {$requestType->label} » : " .
                $requestType->requestFiles()->count() . " fichier(s) y sont associés. Désactivez-le plutôt."
            );
        }

        $label = $requestType->label;
        $requestType->delete();

        return $this->backToTab($cat)->with('success', "« {$label} » supprimé.");
    }

    /* ── Toggle actif ───────────────────────────────────── */

    public function toggleActive(RequestType $requestType)
    {
        $requestType->update(['is_active' => !$requestType->is_active]);
        $cat = $requestType->category;

        $msg = $requestType->is_active
            ? "« {$requestType->label} » activé."
            : "« {$requestType->label} » désactivé.";

        return $this->backToTab($cat)->with('success', $msg);
    }
}
