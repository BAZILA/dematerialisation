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
        /* Les reglages d'un module eteint etaient auparavant retires de la
           liste. Deux defauts en decoulaient.

           Le premier : « payments.enabled » appartient au groupe
           « paiement », pas au groupe « modules ». Il disparaissait donc
           avec les autres, et le module de paiement ne pouvait plus etre
           rallume depuis cet ecran — il fallait passer par la base.

           Le second : on ne pouvait pas preparer une configuration avant
           de l'allumer. Saisir les cles d'API SMS exigeait d'activer les
           notifications d'abord, donc de les laisser partir a moitie
           reglees.

           Tout est desormais affiche. L'ecran signale lui-meme, reglage
           par reglage, ce qui reste sans effet tant que son interrupteur
           est eteint : on voit ce qu'on regle, et on sait quand ca agira. */
        $groups = Setting::allCached()
            // cachet_path est gere par un formulaire d'upload dedie, pas la liste
            ->reject(fn ($s) => $s->key === 'cachet_path')
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
