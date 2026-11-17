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
            // Une fenêtre ouverte depuis un autre site ne garde aucune prise sur celle-ci
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Flash / Acrobat : aucune politique inter-domaines
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // Content-Security-Policy : uniquement nos CDN déclarés.
            // 'unsafe-inline' est requis (scripts/styles inline des vues Blade),
            // mais la CSP bloque déjà tout script chargé depuis un domaine tiers.
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; "
                . "img-src 'self' data: blob:; "
                . "connect-src 'self'; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'self'",
        ];

        // HSTS : uniquement en HTTPS (production) — force le navigateur à rester en HTTPS
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $k => $v) {
            if (!$response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }

        /* Rien de ce que sert l'application ne doit être gardé en cache.

           Deux raisons, toutes deux concrètes ici. Les étudiants se
           connectent souvent depuis un poste partagé — cybercafé, salle
           informatique : sans cet en-tête, le bouton « Précédent » du
           navigateur réaffiche le dossier du précédent après sa
           déconnexion. Et le site est derrière un proxy (Cloudflare) :
           une page de suivi mise en cache par erreur serait servie à un
           autre visiteur.

           Les fichiers statiques (images, CSS) sont servis par Apache et
           ne passent pas par ici : eux restent mis en cache normalement. */
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
