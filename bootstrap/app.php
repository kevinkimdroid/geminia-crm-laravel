<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'erp.api.token' => \App\Http\Middleware\ValidateErpApiToken::class,
            'erp.sync.token' => \App\Http\Middleware\ValidateErpSyncToken::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/admin/erp-clients-import',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
