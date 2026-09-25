<?php

namespace App\Http\Controllers;

use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StreamSignalController extends Controller
{
    public function sendSignal(Request $request, int $classId): JsonResponse
    {
        $participant = $this->resolveParticipant($request, $classId);
        $validated = $request->validate([
            'from_peer_id' => 'required|string|max:64',
            'target_peer_id' => 'required|string|max:64',
            'type' => 'required|string|in:stream_started,stream_stopped,request_stream,stream_offer,stream_answer,ice_candidate',
            'payload' => 'nullable',
        ]);

        if ($validated['from_peer_id'] !== $participant['peer_id']) {
            return $this->forbidden('No puedes suplantar otro participante.');
        }

        if ($participant['role'] === 'host') {
            if (!$this->isAllowedHostSignal($validated, $classId)) {
                return $this->forbidden('Senal del anfitrion no permitida.');
            }
        } elseif (!$this->isAllowedStudentSignal($validated)) {
            return $this->forbidden('Senal del alumno no permitida.');
        }

        $target = $validated['target_peer_id'];
        $cacheKey = "signal_{$classId}_{$target}";
        $pending = Cache::get($cacheKey, []);
        $pending[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'from_peer_id' => $participant['peer_id'],
            'target_peer_id' => $target,
            'type' => $validated['type'],
            'payload' => $validated['payload'] ?? null,
            'timestamp' => microtime(true),
        ];

        Cache::put($cacheKey, array_slice($pending, -30), 30);

        return response()->json(['success' => true]);
    }

    public function getSignals(Request $request, int $classId): JsonResponse
    {
        $participant = $this->resolveParticipant($request, $classId);
        $peerId = (string) $request->query('peer_id', '');

        if ($peerId === '' || $peerId !== $participant['peer_id']) {
            return $this->forbidden('No puedes leer las senales de otro participante.');
        }

        $cacheKey = "signal_{$classId}_{$peerId}";
        $signals = Cache::pull($cacheKey, []);

        if ($participant['role'] !== 'host') {
            $since = (float) $request->query('since', 0);
            $broadcasts = Cache::get("signal_{$classId}_all", []);
            if ($since > 0) {
                $broadcasts = array_values(array_filter($broadcasts, fn($s) => ($s['timestamp'] ?? 0) > $since));
            }
            $signals = array_merge($signals, $broadcasts);
        }

        return response()->json(['success' => true, 'signals' => $signals]);
    }

    protected function resolveParticipant(Request $request, int $classId): array
    {
        $liveClass = LiveClass::findOrFail($classId);
        $sessionToken = $request->header('X-Session-Token') ?: $request->input('session_token');

        if ($sessionToken) {
            $session = ActiveSession::with('accessCode')
                ->where('session_token', $sessionToken)
                ->where('live_class_id', $classId)
                ->where('is_active', true)
                ->first();

            if (!$session || !$session->isLeaseValid() || !$session->accessCode?->isValidForLogin()) {
                abort(401, 'Sesion de alumno no valida para esta clase.');
            }

            return [
                'role' => $session->accessCode->role,
                'peer_id' => 'student_'.$session->id,
            ];
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            abort(401, 'No autorizado.');
        }

        if (!$user->isHost() || (!$user->isAdmin() && $liveClass->host_id !== $user->id)) {
            abort(403, 'No tienes acceso a esta clase.');
        }

        return ['role' => 'host', 'peer_id' => 'host'];
    }

    protected function isAllowedHostSignal(array $signal, int $classId): bool
    {
        $type = $signal['type'];
        $target = $signal['target_peer_id'];

        if (in_array($type, ['stream_started', 'stream_stopped'], true)) {
            return $target === 'all';
        }

        if (!in_array($type, ['stream_offer', 'ice_candidate'], true)) {
            return false;
        }

        if (!preg_match('/^student_(\d+)$/', $target, $matches)) {
            return false;
        }

        $session = ActiveSession::with('accessCode')
            ->whereKey((int) $matches[1])
            ->where('live_class_id', $classId)
            ->where('is_active', true)
            ->first();

        return (bool) ($session && $session->isLeaseValid() && $session->accessCode?->isValidForLogin());
    }

    protected function isAllowedStudentSignal(array $signal): bool
    {
        return $signal['target_peer_id'] === 'host'
            && in_array($signal['type'], ['request_stream', 'stream_answer', 'ice_candidate'], true);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message], 403);
    }
}
