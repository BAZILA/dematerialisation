<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /* Proxys de confiance : la liste vient de config/trustedproxy.php
           (TRUSTED_PROXIES dans le .env). On ne la fige PAS ici : écrite en
           dur à « * », elle faisait croire les en-têtes X-Forwarded-* de
           n'importe qui, donc l'adresse IP que le visiteur voulait bien
           déclarer. Voir le fichier de configuration. */

        /* Hôtes de confiance : seul le domaine d'APP_URL (et ses
           sous-domaines) est accepté. Sans cela, une requête forgée avec
           un autre en-tête Host ferait fabriquer à l'application des liens
           — dans un courriel, par exemple — pointant vers un site tiers.
           Inactif en local et pendant les tests. */
        $middleware->trustHosts();

        // En-têtes de sécurité sur toutes les réponses web + coupure
        // immédiate des sessions dont le compte vient d'être désactivé
        // ou dont le mot de passe a changé + mot de passe provisoire
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureAccountActive::class,
            \App\Http\Middleware\SessionLieeAuMotDePasse::class,
            \App\Http\Middleware\ExigerChangementMotDePasse::class,
        ]);

        // Alias de rôles/permissions (Spatie)
        $middleware->alias([
            // Ferme les routes d'un module désactivé (ex. module:payments.enabled)
            'module'             => \App\Http\Middleware\ModuleActif::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /* Un envoi qui dépasse la limite « post_max_size » du serveur est
           arrêté avant même d'atteindre la validation. Laravel lève alors
           PostTooLargeException — avec un message VIDE par défaut.

           Côté navigateur, un message vide vaut « faux » : l'écran de dépôt
           retombait sur son texte de repli, « Action impossible », sans
           rien dire de la cause. L'usager réessayait le même fichier, en
           boucle, sans comprendre.

           On nomme donc la cause, et on donne la limite réelle du serveur :
           c'est elle qui décide, pas celle de l'application. */
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            $limite = ini_get('post_max_size') ?: 'inconnue';
            $message = "Fichier trop volumineux pour le serveur (limite : {$limite}). "
                . 'Réduisez sa taille — une numérisation en 150 dpi suffit — puis réessayez.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['file' => $message]);
        });
    })->create();
