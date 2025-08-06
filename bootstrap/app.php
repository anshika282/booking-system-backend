<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.check' => EnsureUserBelongsToTenant::class,
        ]);
        $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class, // <-- THIS IS THE BUILT-IN ONE
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
    })->create();
