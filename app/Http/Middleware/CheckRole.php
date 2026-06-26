<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Vérifier que l'utilisateur est authentifié
        if (!auth()->check()) {
            return redirect()->route('login')->withErrors(['error' => 'Vous devez être connecté.']);
        }

        // Vérifier le rôle
        if (auth()->user()->role !== $role) {
            return redirect()->route('dashboard')->withErrors(['error' => 'Accès non autorisé.']);
        }

        return $next($request);
    }
}
