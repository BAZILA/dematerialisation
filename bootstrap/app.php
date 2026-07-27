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
        // Derrière le proxy de l'hébergeur (O2switch) : faire confiance aux
        // en-têtes X-Forwarded-* pour que HTTPS/IP réelles soient détectés.
        $middleware->trustProxies(at: '*');

        // En-têtes de sécurité sur toutes les réponses web
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Alias de rôles/permissions (Spatie)
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
