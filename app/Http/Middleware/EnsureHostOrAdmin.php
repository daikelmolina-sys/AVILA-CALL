<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHostOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isHost()) {
            abort(403, 'Esta seccion es exclusiva para profesores y administradores.');
        }

        return $next($request);
    }
}
