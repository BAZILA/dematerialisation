<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un mot de passe changé ferme les sessions ouvertes avec l'ancien.
 *
 * Sans cela, changer un mot de passe qu'on croit compromis ne sert à
 * rien : celui qui l'a volé reste connecté jusqu'à l'expiration de sa
 * session. Ici, chaque session retient l'empreinte du mot de passe avec
 * lequel elle a été ouverte ; dès que le mot de passe change — par son
 * titulaire ou par l'administration — les autres sessions tombent à
 * leur requête suivante.
 *
 * L'empreinte est rangée par compte : une session qui change
 * d'utilisateur ne confond pas les deux.
 */
class SessionLieeAuMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$request->hasSession()) {
            return $next($request);
        }

        $cle    = 'empreinte_mdp_' . $user->getAuthIdentifier();
        $connue = $request->session()->get($cle);

        if ($connue !== null && !hash_equals($connue, $this->empreinte($user->getAuthPassword()))) {
            \App\Models\AuditLog::record('auth.session_coupee', $user,
                "Session fermée : le mot de passe de {$user->numero_identifiant} a changé depuis son ouverture");

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'numero_identifiant' => 'Votre mot de passe a été modifié. Reconnectez-vous.',
            ]);
        }

        $response = $next($request);

        /* Après la requête : la session EN COURS adopte le mot de passe
           courant. On le relit en base — le contrôleur a pu le modifier
           sur une autre instance du modèle que celle de l'authentification. */
        if (Auth::id() === $user->getAuthIdentifier()) {
            $actuel = ($connue === null || !$request->isMethodSafe())
                ? $user->newQuery()->whereKey($user->getKey())->value('password')
                : $user->getAuthPassword();

            if ($actuel) {
                $request->session()->put($cle, $this->empreinte($actuel));
            }
        }

        return $response;
    }

    private function empreinte(string $hache): string
    {
        return hash('sha256', $hache);
    }
}
