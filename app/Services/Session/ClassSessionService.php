<?php

namespace App\Services\Session;

use App\Models\AccessCode;
use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Services\Media\LiveKitRoomService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ClassSessionService
{
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_SECONDS = 60;
    public const RECONNECTION_GRACE_SECONDS = 60;

    public function __construct(protected LiveKitRoomService $liveKitRooms)
    {
    }

    /**
     * Authenticate a student via access code with atomic session exclusivity
     */
    public function authenticateCode(string $rawCode, string $ipAddress, ?string $userAgent = null): array
    {
        $throttleKey = 'code_auth_' . md5($ipAddress);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return [
                'success' => false,
                'error' => "Demasiados intentos erróneos. Espera {$seconds} segundos antes de intentar nuevamente.",
                'code' => 'RATE_LIMITED',
            ];
        }

        $codeHash = hash('sha256', trim(strtoupper($rawCode)));

        $previousSessionIds = [];
        $result = DB::transaction(function () use ($codeHash, $throttleKey, $ipAddress, $userAgent, &$previousSessionIds) {
            /** @var AccessCode|null $accessCode */
            $accessCode = AccessCode::where('code_hash', $codeHash)
                ->lockForUpdate()
                ->first();

            if (!$accessCode) {
                RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);
                return [
                    'success' => false,
                    'error' => 'Código de acceso no válido o no disponible.',
                    'code' => 'INVALID_CODE',
                ];
            }

            if (!$accessCode->isValidForLogin()) {
                RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);
                $reason = $accessCode->status === 'banned' ? 'Este código ha sido bloqueado por el anfitrión.' : 'Código de acceso no válido o revocado.';
                return [
                    'success' => false,
                    'error' => $reason,
                    'code' => 'CODE_INACTIVE',
                ];
            }

            $liveClass = $accessCode->liveClass()->lockForUpdate()->first();

            if (!$liveClass || $liveClass->isEnded()) {
                return [
                    'success' => false,
                    'error' => 'La clase ya ha finalizado o no está disponible.',
                    'code' => 'CLASS_ENDED',
                ];
            }

            // ATOMIC EXCLUSIVITY: Invalidate any previous active sessions for this access code
            $previousSessionIds = ActiveSession::where('access_code_id', $accessCode->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
            ActiveSession::where('access_code_id', $accessCode->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'revocation_reason' => 'replaced_by_new_device',
                    'updated_at' => now(),
                ]);

            // Create new active session
            $sessionToken = Str::random(60);
            $activeSession = ActiveSession::create([
                'access_code_id' => $accessCode->id,
                'live_class_id' => $liveClass->id,
                'session_token' => $sessionToken,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'is_active' => true,
                'revocation_reason' => null,
                'lease_expires_at' => now()->addSeconds(ActiveSession::LEASE_SECONDS),
                'last_heartbeat_at' => now(),
            ]);

            // Update code state
            $accessCode->update([
                'status' => 'connected',
                'last_used_at' => now(),
            ]);

            // Update class viewers count
            $activeCount = ActiveSession::where('live_class_id', $liveClass->id)
                ->where('is_active', true)
                ->where('lease_expires_at', '>=', now())
                ->count();

            $liveClass->update([
                'current_viewers_count' => $activeCount,
                'peak_viewers_count' => max($liveClass->peak_viewers_count, $activeCount),
            ]);

            RateLimiter::clear($throttleKey);

            return [
                'success' => true,
                'session_token' => $sessionToken,
                'class_id' => $liveClass->id,
                'class_title' => $liveClass->title,
                'class_status' => $liveClass->status,
                'role' => $accessCode->role,
                'student_name' => $accessCode->student_identifier ?: 'Alumno',
                'chat_enabled' => $liveClass->chat_enabled,
                'lease_seconds' => ActiveSession::LEASE_SECONDS,
            ];
        });

        if (($result['success'] ?? false) && $previousSessionIds) {
            $liveClass = LiveClass::find($result['class_id']);
            if ($liveClass) {
                foreach ($previousSessionIds as $sessionId) {
                    $this->liveKitRooms->removeStudentSession($liveClass, (int) $sessionId);
                }
            }
        }

        return $result;
    }

    /**
     * Process heartbeat and verify session validity
     */
    public function processHeartbeat(string $sessionToken): array
    {
        /** @var ActiveSession|null $session */
        $session = ActiveSession::where('session_token', $sessionToken)->first();

        if (!$session) {
            return [
                'status' => 'revoked',
                'reason' => 'session_not_found',
                'message' => 'La sesión no existe o es inválida.',
            ];
        }

        if (!$session->is_active) {
            $messages = [
                'replaced_by_new_device' => 'Tu sesión se cerró porque este código ingresó desde otro dispositivo.',
                'kicked_by_moderator' => 'Has sido expulsado de la clase por un moderador.',
                'banned' => 'Tu código de acceso ha sido bloqueado.',
                'class_ended' => 'La clase en vivo ha finalizado.',
                'manual_logout' => 'Has cerrado la sesión.',
            ];

            return [
                'status' => 'revoked',
                'reason' => $session->revocation_reason ?: 'inactive',
                'message' => $messages[$session->revocation_reason] ?? 'Tu sesión ha sido revocada.',
            ];
        }

        $accessCode = $session->accessCode;
        if (!$accessCode || $accessCode->status === 'revoked' || $accessCode->status === 'banned') {
            $session->update([
                'is_active' => false,
                'revocation_reason' => $accessCode->status ?? 'revoked',
            ]);

            return [
                'status' => 'revoked',
                'reason' => $accessCode->status ?? 'revoked',
                'message' => 'Tu código de acceso ha sido revocado o bloqueado.',
            ];
        }

        $liveClass = $session->liveClass;
        if (!$liveClass || $liveClass->isEnded()) {
            $session->update([
                'is_active' => false,
                'revocation_reason' => 'class_ended',
            ]);

            return [
                'status' => 'revoked',
                'reason' => 'class_ended',
                'message' => 'La clase en vivo ha finalizado.',
            ];
        }

        // Renew lease
        $session->update([
            'lease_expires_at' => now()->addSeconds(ActiveSession::LEASE_SECONDS),
            'last_heartbeat_at' => now(),
        ]);

        return [
            'status' => 'active',
            'chat_enabled' => (bool)$liveClass->chat_enabled,
            'class_status' => $liveClass->status,
            'role' => $accessCode->role,
            'current_viewers' => $liveClass->current_viewers_count,
        ];
    }

    /**
     * Terminate an active session (student leaves)
     */
    public function logoutSession(string $sessionToken): bool
    {
        $session = ActiveSession::where('session_token', $sessionToken)->first();
        if (!$session) {
            return false;
        }

        DB::transaction(function () use ($session) {
            $session->update([
                'is_active' => false,
                'revocation_reason' => 'manual_logout',
            ]);

            $code = $session->accessCode;
            if ($code && $code->status === 'connected') {
                $code->update(['status' => 'available']);
            }

            $liveClass = $session->liveClass;
            if ($liveClass) {
                $count = ActiveSession::where('live_class_id', $liveClass->id)
                    ->where('is_active', true)
                    ->where('lease_expires_at', '>=', now())
                    ->count();
                $liveClass->update(['current_viewers_count' => $count]);
            }
        });

        if ($session->liveClass) {
            $this->liveKitRooms->removeStudentSession($session->liveClass, $session->id);
        }

        return true;
    }
}
