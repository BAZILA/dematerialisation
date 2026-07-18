<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (défense en profondeur) appliqués à toutes les réponses.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Anti-clickjacking
            'X-Frame-Options' => 'SAMEORIGIN',
            // Empêche le MIME-sniffing
            'X-Content-Type-Options' => 'nosniff',
            // Ne fuite pas l'URL complète vers les tiers
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Restreint les API navigateur sensibles
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($headers as $k => $v) {
            if (!$response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }

        return $response;
    }
}
