<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme les routes d'un module désactivé.
 *
 * Masquer les liens ne suffit pas : sans ce garde, une URL connue
 * resterait accessible. Un module éteint répond 404, comme s'il
 * n'existait pas.
 *
 * Usage : ->middleware('module:payments.enabled')
 */
class ModuleActif
{
    public function handle(Request $request, Closure $next, string $cle): Response
    {
        abort_unless((bool) Setting::get($cle, false), 404);

        return $next($request);
    }
}
