<?php

use App\Http\Middleware\Cors;
use App\Http\Middleware\EnsureEmployer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            Cors::class,
        ]);
        $middleware->alias([
            'checkPermission' => \App\Http\Middleware\CheckPermission::class,
            'checkRole' => \App\Http\Middleware\CheckRole::class,
            'multiAuth' => \App\Http\Middleware\MultiAuthMiddleware::class,
            'apiKey' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'apiRateLimit' => \App\Http\Middleware\ApiRateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
