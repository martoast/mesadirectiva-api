<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\JsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(JsonResponse::class);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->api(append: [
            EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
