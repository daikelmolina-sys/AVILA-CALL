<?php

namespace App\Services\Chat;

use App\Models\AccessCode;
use App\Models\ActiveSession;
use App\Models\ChatMessage;
use App\Models\LiveClass;
use App\Models\ModerationLog;
use App\Models\User;
use App\Services\Media\LiveKitRoomService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class ChatModerationService
{
    public const MAX_MESSAGE_LENGTH = 350;
    public const CHAT_RATE_LIMIT_PER_MINUTE = 15;

    public function __construct(protected LiveKitRoomService $liveKitRooms)
    {
    }

    /**
     * Post a message to a live class chat with server-side validation
     */
    public function postMessage(
        int $liveClassId,
        string $messageText,
        ?string $sessionToken = null,
        ?User $user = null
    ): array {
        $liveClass = LiveClass::find($liveClassId);
        if (!$liveClass || $liveClass->isEnded()) {
            return [
                'success' => false,
                'error' => 'La clase no está activa.',
                'code' => 'CLASS_INACTIVE',
            ];
        }

        // Determine sender identity and role
        $senderName = '';
        $senderRole = 'student';
        $accessCode = null;
        $userId = null;
        $throttleKey = '';

        if ($user) {
            if (!$user->isHost() || (!$user->isAdmin() && $liveClass->host_id !== $user->id)) {
                return [
                    'success' => false,
                    'error' => 'No tienes acceso a esta clase.',
                    'code' => 'FORBIDDEN',
                ];
            }

            $senderName = $user->name;
            $senderRole = 'host';
            $userId = $user->id;
            $throttleKey = 'chat_user_' . $user->id;
        } elseif ($sessionToken) {
            $session = ActiveSession::where('session_token', $sessionToken)
                ->where('live_class_id', $liveClassId)
                ->where('is_active', true)
                ->first();

            if (!$session || !$session->isLeaseValid()) {
                return [
                    'success' => false,
                    'error' => 'Sesión no válida o expirada.',
                    'code' => 'INVALID_SESSION',
                ];
            }

            $accessCode = $session->accessCode;
            if (!$accessCode || $accessCode->status === 'banned' || $accessCode->status === 'revoked') {
                return [
                    'success' => false,
                    'error' => 'Acceso denegado.',
                    'code' => 'FORBIDDEN',
                ];
            }

            $senderName = $accessCode->student_identifier ?: ('Alumno ' . substr($accessCode->code_prefix, 0, 6));
            $senderRole = $accessCode->role; // 'student' or 'moderator'
            $throttleKey = 'chat_code_' . $accessCode->id;
        } else {
            return [
                'success' => false,
                'error' => 'No autorizado para enviar mensajes.',
                'code' => 'UNAUTHORIZED',
            ];
        }

        // CRITICAL SERVER-SIDE CHECK: Chat open/closed
        // Hosts and moderators can always send operational announcements even if chat is closed
        if (!$liveClass->chat_enabled && $senderRole === 'student') {
            return [
                'success' => false,
                'error' => 'El chat se encuentra deshabilitado temporalmente por el anfitrión.',
                'code' => 'CHAT_DISABLED',
            ];
        }

        // Rate limiting for students and moderators
        if ($senderRole !== 'host') {
            if (RateLimiter::tooManyAttempts($throttleKey, self::CHAT_RATE_LIMIT_PER_MINUTE)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return [
                    'success' => false,
                    'error' => "Envías mensajes con demasiada rapidez. Espera {$seconds} segundos.",
                    'code' => 'RATE_LIMITED',
                ];
            }
            RateLimiter::hit($throttleKey, 60);
        }

        // Content validation and sanitization (Prevent XSS and enforce length)
        $cleanMessage = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $messageText);
        $cleanMessage = trim(strip_tags($cleanMessage));
        if ($cleanMessage === '') {
            return [
                'success' => false,
                'error' => 'El mensaje no puede estar vacío.',
                'code' => 'EMPTY_MESSAGE',
            ];
        }

        if (mb_strlen($cleanMessage) > self::MAX_MESSAGE_LENGTH) {
            return [
                'success' => false,
                'error' => 'El mensaje excede el límite de ' . self::MAX_MESSAGE_LENGTH . ' caracteres.',
                'code' => 'MESSAGE_TOO_LONG',
            ];
        }

        // Save message safely
        $msg = ChatMessage::create([
            'live_class_id' => $liveClass->id,
            'access_code_id' => $accessCode ? $accessCode->id : null,
            'user_id' => $userId,
            'sender_name' => $senderName,
            'sender_role' => $senderRole,
            'message' => $cleanMessage,
            'is_deleted' => false,
        ]);

        return [
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'sender_name' => $msg->sender_name,
                'sender_role' => $msg->sender_role,
                'message' => e($msg->message),
                'created_at' => $msg->created_at->format('H:i:s'),
                'is_deleted' => false,
            ],
        ];
    }

    /**
     * Get recent messages for a specific class (strictly isolating classes)
     */
    public function getRecentMessages(int $liveClassId, ?int $afterId = null): array
    {
        $query = ChatMessage::where('live_class_id', $liveClassId)
            ->where('is_deleted', false)
            ->orderBy('id', 'asc');

        if ($afterId) {
            $query->where('id', '>', $afterId);
        } else {
            // Last 100 messages on initial join
            $query->take(100);
        }

        return $query->get()->map(function ($msg) {
            return [
                'id' => $msg->id,
                'sender_name' => $msg->sender_name,
                'sender_role' => $msg->sender_role,
                'message' => e($msg->message),
                'created_at' => $msg->created_at->format('H:i:s'),
                'is_deleted' => false,
            ];
        })->toArray();
    }

    /**
     * Toggle chat status on/off (Host only)
     */
    public function toggleChatStatus(LiveClass $liveClass, bool $enabled, string $actorName, int $actorId): void
    {
        $liveClass->update(['chat_enabled' => $enabled]);

        ModerationLog::create([
            'live_class_id' => $liveClass->id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => 'toggle_chat',
            'details' => $enabled ? 'Chat habilitado' : 'Chat cerrado',
        ]);
    }

    /**
     * Delete a chat message (Host or Moderator)
     */
    public function deleteMessage(int $messageId, int $liveClassId, string $actorName, int $actorId, string $actorType = 'user'): bool
    {
        $message = ChatMessage::where('id', $messageId)
            ->where('live_class_id', $liveClassId)
            ->first();

        if (!$message) {
            return false;
        }

        $message->update([
            'is_deleted' => true,
            'deleted_by_user_id' => $actorType === 'user' ? $actorId : null,
        ]);

        ModerationLog::create([
            'live_class_id' => $liveClassId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => 'delete_message',
            'target_type' => 'chat_message',
            'target_id' => $message->id,
            'target_name' => $message->sender_name,
            'details' => 'Mensaje eliminado',
        ]);

        return true;
    }

    /**
     * Kick student from current session (Host or Moderator)
     */
    public function kickStudent(int $accessCodeId, int $liveClassId, string $actorName, int $actorId, string $actorType = 'user', ?string $reason = null): bool
    {
        $sessionIds = [];
        $result = DB::transaction(function () use ($accessCodeId, $liveClassId, $actorName, $actorId, $actorType, $reason, &$sessionIds) {
            $code = AccessCode::where('id', $accessCodeId)
                ->where('live_class_id', $liveClassId)
                ->first();

            if (!$code) {
                return false;
            }

            // Immediately invalidate all active sessions for this code
            $sessionIds = ActiveSession::where('access_code_id', $code->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
            ActiveSession::where('access_code_id', $code->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'revocation_reason' => 'kicked_by_moderator',
                    'updated_at' => now(),
                ]);

            $code->update(['status' => 'available']); // Can re-enter unless banned

            ModerationLog::create([
                'live_class_id' => $liveClassId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'action' => 'kick_student',
                'target_type' => 'access_code',
                'target_id' => $code->id,
                'target_name' => $code->student_identifier ?: $code->code_prefix,
                'details' => $reason ?: 'Expulsado de la sesión en vivo',
            ]);

            return true;
        });

        if ($result && $sessionIds && ($liveClass = LiveClass::find($liveClassId))) {
            foreach ($sessionIds as $sessionId) {
                $this->liveKitRooms->removeStudentSession($liveClass, (int) $sessionId);
            }
        }

        return $result;
    }

    /**
     * Ban student access code (Permanent lockout until unbanned)
     */
    public function banStudentCode(int $accessCodeId, int $liveClassId, string $actorName, int $actorId, string $actorType = 'user', ?string $reason = null): bool
    {
        $sessionIds = [];
        $result = DB::transaction(function () use ($accessCodeId, $liveClassId, $actorName, $actorId, $actorType, $reason, &$sessionIds) {
            $code = AccessCode::where('id', $accessCodeId)
                ->where('live_class_id', $liveClassId)
                ->first();

            if (!$code) {
                return false;
            }

            $code->update([
                'status' => 'banned',
                'banned_at' => now(),
            ]);

            $sessionIds = ActiveSession::where('access_code_id', $code->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
            ActiveSession::where('access_code_id', $code->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'revocation_reason' => 'banned',
                    'updated_at' => now(),
                ]);

            ModerationLog::create([
                'live_class_id' => $liveClassId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'action' => 'ban_code',
                'target_type' => 'access_code',
                'target_id' => $code->id,
                'target_name' => $code->student_identifier ?: $code->code_prefix,
                'details' => $reason ?: 'Código bloqueado permanentemente',
            ]);

            return true;
        });

        if ($result && $sessionIds && ($liveClass = LiveClass::find($liveClassId))) {
            foreach ($sessionIds as $sessionId) {
                $this->liveKitRooms->removeStudentSession($liveClass, (int) $sessionId);
            }
        }

        return $result;
    }

    /**
     * Promote/demote moderator role on access code (Host only)
     */
    public function setModeratorRole(int $accessCodeId, int $liveClassId, bool $isModerator, string $actorName, int $actorId): bool
    {
        $code = AccessCode::where('id', $accessCodeId)
            ->where('live_class_id', $liveClassId)
            ->first();

        if (!$code) {
            return false;
        }

        $newRole = $isModerator ? 'moderator' : 'student';
        $code->update(['role' => $newRole]);

        ModerationLog::create([
            'live_class_id' => $liveClassId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => $isModerator ? 'promote_moderator' : 'demote_moderator',
            'target_type' => 'access_code',
            'target_id' => $code->id,
            'target_name' => $code->student_identifier ?: $code->code_prefix,
            'details' => "Rol actualizado a {$newRole}",
        ]);

        return true;
    }

    /**
     * Purge chat messages and moderation logs older than specified retention days (default: 30 days)
     */
    public function purgeOldMessagesAndLogs(int $days = 30): array
    {
        $threshold = now()->subDays($days);

        $deletedMessagesCount = ChatMessage::where('created_at', '<', $threshold)->delete();
        $deletedLogsCount = ModerationLog::where('created_at', '<', $threshold)->delete();

        return [
            'threshold_date' => $threshold->toDateTimeString(),
            'deleted_messages' => $deletedMessagesCount,
            'deleted_logs' => $deletedLogsCount,
        ];
    }

    /**
     * Purge chat messages and moderation logs once a class has been ended
     * for at least the configured number of hours.
     */
    public function purgeEndedClassMessagesAndLogs(int $hours = 1): array
    {
        $threshold = now()->subHours($hours);

        $endedClassIds = LiveClass::query()
            ->where('status', 'ended')
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', $threshold)
            ->select('id');

        $eligibleClassesCount = (clone $endedClassIds)->count();
        $deletedMessagesCount = ChatMessage::whereIn('live_class_id', clone $endedClassIds)->delete();
        $deletedLogsCount = ModerationLog::whereIn('live_class_id', clone $endedClassIds)->delete();

        return [
            'threshold_date' => $threshold->toDateTimeString(),
            'eligible_classes' => $eligibleClassesCount,
            'deleted_messages' => $deletedMessagesCount,
            'deleted_logs' => $deletedLogsCount,
        ];
    }
}
