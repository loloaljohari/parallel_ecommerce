<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'aop.log' => \App\Http\Middleware\Aspects\BeforeAfterLogger::class,
            'aop.stock' => \App\Http\Middleware\Aspects\StockGuard::class,
            'aop.load' => \App\Http\Middleware\Aspects\ConcurrencyLimiter::class,

            ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();



