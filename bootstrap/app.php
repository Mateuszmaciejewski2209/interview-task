<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Notifications\Presentation\Console\SimulateDeliveredWebhookCommand;
use Modules\Shared\Infrastructure\Http\FaultResponses;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // NotificationServiceProvider is deferred, so the command is registered here
    ->withCommands([SimulateDeliveredWebhookCommand::class])
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        FaultResponses::register($exceptions);
    })->create();
