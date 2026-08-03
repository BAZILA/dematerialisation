<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuperAdminController extends Controller
{
    /** Page de paramétrage de la plateforme. */
    public function settings()
    {
        // Module de paiement éteint : tous ses réglages disparaissent.
        // Seul l'interrupteur général (rangé dans « Modules ») reste visible.
        $paiementActif = \App\Models\Paiement::moduleActif();

        // Même principe pour les notifications : module éteint, réglages masqués
        $notifsActives = \App\Models\NotificationEnvoyee::moduleActif();

        // Les délais de relance n'ont de sens qu'une fois la relance armée
        $relancesActives = (bool) Setting::get('relances.enabled', false);

        $groups = Setting::allCached()
            // cachet_path est géré par un formulaire d'upload dédié, pas la liste
            ->reject(fn ($s) => $s->key === 'cachet_path')
            ->reject(fn ($s) => !$paiementActif && (
                $s->group === 'paiement' || $s->key === 'candidature.fees.enabled'
            ))
            ->reject(fn ($s) => !$notifsActives && $s->group === 'notifications')
            ->reject(fn ($s) => !$relancesActives && $s->group === 'relances')
            ->groupBy('group');
        $hasCachet = (bool) Setting::get('cachet_path');

        return view('superadmin.settings', compact('groups', 'hasCachet'));
    }

    /** Upload du cachet officiel (image apposée sur le verso des fiches). */
    public function uploadCachet(Request $request)
    {
        $request->validate([
            'cachet' => 'required|image|mimes:png|max:2048',
        ], [], ['cachet' => 'cachet']);

        // Supprimer l'ancien cachet le cas échéant
        $old = Setting::get('cachet_path');
        if ($old && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }

        $path = $request->file('cachet')->storeAs(
            'branding',
            'cachet_' . time() . '.png',
            'local'
        );

        Setting::set('cachet_path', $path);

        return back()->with('success', 'Cachet officiel enregistré.');
    }

    /** Supprime le cachet officiel. */
    public function deleteCachet()
    {
        $old = Setting::get('cachet_path');
        if ($old && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }
        Setting::set('cachet_path', '');

        return back()->with('success', 'Cachet supprimé.');
    }

    /** Sert l'image du cachet (aperçu). */
    public function cachet()
    {
        $path = Setting::get('cachet_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    /** Enregistre les réglages soumis. */
    public function updateSettings(Request $request)
    {
        $posted = $request->input('settings', []);

        foreach (Setting::all() as $s) {
            if ($s->type === 'boolean') {
                Setting::set($s->key, array_key_exists($s->key, $posted));
            } elseif (array_key_exists($s->key, $posted)) {
                Setting::set($s->key, (string) $posted[$s->key]);
            }
        }

        \App\Models\AuditLog::record('parametres.modifie', null, 'Paramètres de la plateforme modifiés');

        return redirect()->route('superadmin.settings')->with('success', 'Paramètres enregistrés.');
    }
}
