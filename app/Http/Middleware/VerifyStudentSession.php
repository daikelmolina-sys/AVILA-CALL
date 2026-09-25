<?php

namespace App\Http\Middleware;

use App\Models\ActiveSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStudentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Session-Token')
            ?: $request->cookie('avila_session_token')
            ?: $request->session()->get('student_session_token');

        if (!$token) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sesión requerida.',
                    'code' => 'UNAUTHORIZED',
                ], 401);
            }
            return redirect()->route('student.login')->with('error', 'Por favor ingresa con tu código individual.');
        }

        $session = ActiveSession::where('session_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$session || !$session->isLeaseValid()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tu sesión ha expirado o fue abierta en otro dispositivo.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }
            return redirect()->route('student.login')->with('error', 'Tu sesión ha expirado o se inició en otro dispositivo.');
        }

        $request->attributes->set('active_session', $session);
        $request->attributes->set('access_code', $session->accessCode);

        return $next($request);
    }
}
