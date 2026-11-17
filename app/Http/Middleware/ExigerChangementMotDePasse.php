<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant qu'un mot de passe provisoire n'a pas été remplacé, le compte
 * n'ouvre qu'une seule page : celle qui permet de le remplacer.
 *
 * Le mot de passe remis par l'administration est connu d'elle. Le
 * laisser servir indéfiniment reviendrait à partager le compte.
 */
class ExigerChangementMotDePasse
{
    /** Ce qui reste accessible : changer le mot de passe, la 2FA, sortir. */
    private const ROUTES_LIBRES = [
        'compte.mot-de-passe', 'compte.mot-de-passe.changer',
        '2fa.challenge', '2fa.verify', 'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->doit_changer_mot_de_passe
            && !$request->routeIs(...self::ROUTES_LIBRES)) {

            $message = 'Votre mot de passe est provisoire : choisissez-en un nouveau pour continuer.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 423);
            }

            return redirect()->route('compte.mot-de-passe')->with('info', $message);
        }

        return $next($request);
    }
}
