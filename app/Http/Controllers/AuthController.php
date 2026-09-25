<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Session\ClassSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    protected ClassSessionService $sessionService;

    public function __construct(ClassSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Show home/landing page: displays student code login box and host access link
     */
    public function showStudentLogin(): View
    {
        return view('auth.student_login');
    }

    /**
     * Show host/admin login page
     */
    public function showHostLogin(): View
    {
        return view('auth.host_login');
    }

    /**
     * Process host/admin credentials
     */
    public function loginHost(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (!Auth::user()?->isHost()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Esta cuenta no tiene acceso al panel de profesor.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->intended(route('admin.classes.index'));
        }

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no son correctas.',
        ])->onlyInput('email');
    }

    /**
     * Host/admin logout
     */
    public function logoutHost(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('host.login');
    }

    /**
     * Authenticate student using single-use access code
     */
    public function authenticateStudentCode(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'access_code' => 'required|string|min:6|max:64',
        ]);

        $rawCode = trim($request->input('access_code'));
        $ip = $request->ip() ?: '127.0.0.1';
        $ua = $request->userAgent();

        $result = $this->sessionService->authenticateCode($rawCode, $ip, $ua);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                return response()->json($result, 422);
            }
            return back()->with('error', $result['error'])->withInput();
        }

        // Store session token in Laravel session & cookie for resilience
        $request->session()->put('student_session_token', $result['session_token']);
        $request->session()->put('student_class_id', $result['class_id']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => route('student.room', ['class' => $result['class_id']]),
                'session_token' => $result['session_token'],
                'role' => $result['role'],
            ]);
        }

        return redirect()->route('student.room', ['class' => $result['class_id']])
            ->withCookie(cookie('avila_session_token', $result['session_token'], 120, null, null, false, true));
    }

    /**
     * Student heartbeat ping endpoint
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $token = $request->header('X-Session-Token')
            ?: $request->input('session_token')
            ?: $request->session()->get('student_session_token');

        if (!$token) {
            return response()->json([
                'status' => 'revoked',
                'reason' => 'no_token',
                'message' => 'Sesión no especificada.',
            ], 401);
        }

        $result = $this->sessionService->processHeartbeat($token);

        $statusCode = ($result['status'] === 'active') ? 200 : 401;
        return response()->json($result, $statusCode);
    }

    /**
     * Student voluntary logout
     */
    public function logoutStudent(Request $request): RedirectResponse|JsonResponse
    {
        $token = $request->header('X-Session-Token')
            ?: $request->session()->get('student_session_token');

        if ($token) {
            $this->sessionService->logoutSession($token);
        }

        $request->session()->forget(['student_session_token', 'student_class_id']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('student.login')->with('info', 'Has salido de la clase.')
            ->withoutCookie('avila_session_token');
    }
}
