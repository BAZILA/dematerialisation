<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement l'accès d'un compte désactivé.
 *
 * Bloquer la connexion ne suffit pas : un agent désactivé pendant qu'il
 * est connecté conserverait sa session jusqu'à expiration. Ici, la
 * désactivation prend effet à sa requête suivante.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->is_active) {
            $user = Auth::user();

            \App\Models\AuditLog::record('auth.session_coupee', $user,
                "Session interrompue : le compte {$user->numero_identifiant} a été désactivé");

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'numero_identifiant' => "Ce compte a été désactivé. Rapprochez-vous de l'administration.",
            ]);
        }

        return $next($request);
    }
}
