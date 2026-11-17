<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige la validation du code 2FA (TOTP) après le login pour les comptes
 * qui ont activé la double authentification. Appliqué aux espaces
 * back-office et super-admin ; les comptes sans 2FA passent directement.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasTwoFactor() && !$request->session()->get('2fa_passed')) {
            return redirect()->route('2fa.challenge');
        }

        return $next($request);
    }
}
