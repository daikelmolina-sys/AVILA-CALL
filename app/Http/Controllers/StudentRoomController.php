<?php

namespace App\Http\Controllers;

use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Services\Media\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentRoomController extends Controller
{
    protected MediaManager $mediaManager;

    public function __construct(MediaManager $mediaManager)
    {
        $this->mediaManager = $mediaManager;
    }

    /**
     * Display the student live class viewing room
     */
    public function show(Request $request, int $classId): View|RedirectResponse
    {
        $sessionToken = $request->header('X-Session-Token')
            ?: $request->cookie('avila_session_token')
            ?: $request->session()->get('student_session_token');

        if (!$sessionToken) {
            return redirect()->route('student.login')
                ->with('error', 'Debes ingresar tu código de acceso para entrar a la clase.');
        }

        $session = ActiveSession::where('session_token', $sessionToken)
            ->where('is_active', true)
            ->first();

        if (!$session || !$session->isLeaseValid()) {
            return redirect()->route('student.login')
                ->with('error', 'Tu sesión ha expirado o se inició en otro dispositivo.');
        }

        // STRICT ISOLATION: Student cannot view another class than the one their code belongs to
        if ($session->live_class_id !== $classId) {
            return redirect()->route('student.room', ['class' => $session->live_class_id])
                ->with('error', 'Tu código corresponde a otra clase.');
        }

        $liveClass = LiveClass::with('host')->findOrFail($classId);

        if ($liveClass->isEnded()) {
            return redirect()->route('student.login')
                ->with('info', 'Esta clase ya ha concluido. Recuerda que AVILA CALL no ofrece grabaciones posteriores.');
        }

        $accessCode = $session->accessCode;
        $adapter = $this->mediaManager->getAdapterForClass($liveClass);
        if (!$adapter->isConfigured()) {
            return redirect()->route('student.login')
                ->with('error', 'La transmision de esta clase aun no esta configurada.');
        }

        $mediaConfig = $adapter->getClientConfig($liveClass, $accessCode->role, 'student_'.$session->id);

        return view('student.room', compact('liveClass', 'session', 'accessCode', 'mediaConfig', 'sessionToken'));
    }
}
