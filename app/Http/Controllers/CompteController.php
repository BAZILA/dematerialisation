<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\MotDePasse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Ce que chacun peut faire sur SON PROPRE compte.
 *
 * Jusqu'ici, personne ne pouvait changer son mot de passe lui-même : il
 * fallait qu'un gestionnaire de comptes le fasse — et le connaisse. Un
 * mot de passe que deux personnes connaissent n'authentifie plus
 * personne.
 */
class CompteController extends Controller
{
    public function motDePasse(Request $request)
    {
        return view('compte.mot-de-passe', [
            'impose' => (bool) $request->user()->doit_changer_mot_de_passe,
        ]);
    }

    public function changerMotDePasse(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'mot_de_passe_actuel' => 'required|string',
            'password'            => ['required', 'confirmed', 'different:mot_de_passe_actuel', MotDePasse::pour($user)],
        ], [
            'password.different' => "Le nouveau mot de passe doit être différent de l'ancien.",
        ], [
            'mot_de_passe_actuel' => 'mot de passe actuel',
            'password'            => 'nouveau mot de passe',
        ]);

        if (!Hash::check($request->mot_de_passe_actuel, $user->password)) {
            AuditLog::record('compte.mot_de_passe_refuse', $user,
                "Changement de mot de passe refusé pour {$user->numero_identifiant} : mot de passe actuel incorrect");

            return back()->withErrors(['mot_de_passe_actuel' => 'Le mot de passe actuel est incorrect.']);
        }

        $user->forceFill([
            'password'                  => Hash::make($request->password),
            'doit_changer_mot_de_passe' => false,
        ])->save();

        /* Les autres appareils encore connectés avec l'ancien mot de passe
           tombent à leur requête suivante (SessionLieeAuMotDePasse) : c'est
           tout l'intérêt de le changer quand on craint qu'il ait fuité.
           La session en cours, elle, est gardée. */
        $request->session()->regenerate();

        AuditLog::record('compte.mot_de_passe_change', $user,
            "Mot de passe changé par son titulaire ({$user->numero_identifiant})");

        return redirect()->route('dashboard')
            ->with('success', 'Votre mot de passe a été changé.');
    }
}
