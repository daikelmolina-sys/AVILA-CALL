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
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'host_or_admin' => \App\Http\Middleware\EnsureHostOrAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'student/heartbeat',
            'student/logout',
            'api/class/*/stream/*',
            'api/class/*/chat/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión expiró por inactividad. Por favor recarga la página.',
                ], 419);
            }
            return redirect()->back()
                ->withInput($request->except(['password', '_token']))
                ->with('error', 'Tu sesión expiró por inactividad. Por favor intenta de nuevo.');
        });
    })->create();
