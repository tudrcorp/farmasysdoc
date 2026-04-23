<?php

use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureLocalEnvironment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('accounts-payable:recalculate-current-balances')
            ->dailyAt('07:00')
            ->timezone('America/Caracas');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.client' => AuthenticateApiClient::class,
            'local' => EnsureLocalEnvironment::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
