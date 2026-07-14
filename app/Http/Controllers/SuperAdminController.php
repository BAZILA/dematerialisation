<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    /** Page de paramétrage de la plateforme. */
    public function settings()
    {
        $groups = Setting::allCached()->groupBy('group');

        return view('superadmin.settings', compact('groups'));
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

        return redirect()->route('superadmin.settings')->with('success', 'Paramètres enregistrés.');
    }
}
