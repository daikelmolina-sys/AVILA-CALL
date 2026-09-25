<?php

namespace App\Http\Controllers;

use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Models\User;
use App\Services\Chat\ChatModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatApiController extends Controller
{
    public function __construct(protected ChatModerationService $chatService)
    {
    }

    public function getMessages(Request $request, int $classId): JsonResponse
    {
        $afterId = $request->query('after_id') ? (int) $request->query('after_id') : null;
        $liveClass = LiveClass::findOrFail($classId);
        $this->validateParticipantAccess($request, $classId);

        return response()->json([
            'success' => true,
            'chat_enabled' => (bool) $liveClass->chat_enabled,
            'class_status' => $liveClass->status,
            'messages' => $this->chatService->getRecentMessages($classId, $afterId),
        ]);
    }

    public function sendMessage(Request $request, int $classId): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:400']);

        /** @var User|null $user */
        $user = Auth::user();
        $sessionToken = $this->sessionToken($request);

        if (!$user && !$sessionToken) {
            return response()->json([
                'success' => false,
                'error' => 'No autorizado.',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $result = $this->chatService->postMessage(
            $classId,
            $request->input('message'),
            $sessionToken,
            $sessionToken ? null : $user,
        );

        $statusCode = match ($result['code'] ?? null) {
            'UNAUTHORIZED', 'INVALID_SESSION' => 401,
            'FORBIDDEN' => 403,
            default => $result['success'] ? 201 : 422,
        };

        return response()->json($result, $statusCode);
    }

    public function toggleChat(Request $request, int $classId): JsonResponse
    {
        $liveClass = LiveClass::findOrFail($classId);
        $user = Auth::user();

        if ($this->sessionToken($request) || !$user?->isHost() || (!$user->isAdmin() && $liveClass->host_id !== $user->id)) {
            return response()->json(['success' => false, 'error' => 'Solo el anfitrion puede controlar el chat.'], 403);
        }

        $request->validate(['enabled' => 'required|boolean']);
        $enabled = $request->boolean('enabled');
        $this->chatService->toggleChatStatus($liveClass, $enabled, $user->name, $user->id);

        return response()->json(['success' => true, 'chat_enabled' => $enabled]);
    }

    public function deleteMessage(Request $request, int $classId): JsonResponse
    {
        $request->validate(['message_id' => 'required|integer']);
        $actor = $this->resolveModeratorActor($request, $classId);
        if (!$actor['allowed']) {
            return response()->json(['success' => false, 'error' => 'No tienes permisos de moderacion.'], 403);
        }

        $deleted = $this->chatService->deleteMessage(
            (int) $request->input('message_id'),
            $classId,
            $actor['name'],
            $actor['id'],
            $actor['type'],
        );

        return response()->json(['success' => $deleted]);
    }

    public function kickStudent(Request $request, int $classId): JsonResponse
    {
        $request->validate([
            'access_code_id' => 'required|integer',
            'reason' => 'nullable|string|max:200',
        ]);
        $actor = $this->resolveModeratorActor($request, $classId);
        if (!$actor['allowed']) {
            return response()->json(['success' => false, 'error' => 'No tienes permisos de moderacion.'], 403);
        }

        $kicked = $this->chatService->kickStudent(
            (int) $request->input('access_code_id'),
            $classId,
            $actor['name'],
            $actor['id'],
            $actor['type'],
            $request->input('reason'),
        );

        return response()->json(['success' => $kicked]);
    }

    public function banStudent(Request $request, int $classId): JsonResponse
    {
        $request->validate([
            'access_code_id' => 'required|integer',
            'reason' => 'nullable|string|max:200',
        ]);
        $actor = $this->resolveModeratorActor($request, $classId);
        if (!$actor['allowed']) {
            return response()->json(['success' => false, 'error' => 'No tienes permisos de moderacion.'], 403);
        }

        $banned = $this->chatService->banStudentCode(
            (int) $request->input('access_code_id'),
            $classId,
            $actor['name'],
            $actor['id'],
            $actor['type'],
            $request->input('reason'),
        );

        return response()->json(['success' => $banned]);
    }

    public function setModeratorRole(Request $request, int $classId): JsonResponse
    {
        $liveClass = LiveClass::findOrFail($classId);
        $user = Auth::user();

        if ($this->sessionToken($request) || !$user?->isHost() || (!$user->isAdmin() && $liveClass->host_id !== $user->id)) {
            return response()->json(['success' => false, 'error' => 'Solo el anfitrion puede nombrar moderadores.'], 403);
        }

        $request->validate([
            'access_code_id' => 'required|integer',
            'is_moderator' => 'required|boolean',
        ]);
        $success = $this->chatService->setModeratorRole(
            (int) $request->input('access_code_id'),
            $classId,
            $request->boolean('is_moderator'),
            $user->name,
            $user->id,
        );

        return response()->json(['success' => $success]);
    }

    protected function validateParticipantAccess(Request $request, int $classId): void
    {
        if ($sessionToken = $this->sessionToken($request)) {
            $session = ActiveSession::where('session_token', $sessionToken)
                ->where('live_class_id', $classId)
                ->where('is_active', true)
                ->first();

            if (!$session || !$session->isLeaseValid() || !$session->accessCode?->isValidForLogin()) {
                abort(401, 'Sesion no valida para esta clase.');
            }

            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        $liveClass = LiveClass::findOrFail($classId);

        if (!$user) {
            abort(401, 'No autorizado.');
        }

        if (!$user->isHost() || (!$user->isAdmin() && $liveClass->host_id !== $user->id)) {
            abort(403, 'No tienes acceso a esta clase.');
        }
    }

    protected function resolveModeratorActor(Request $request, int $classId): array
    {
        $liveClass = LiveClass::find($classId);

        if ($sessionToken = $this->sessionToken($request)) {
            $session = ActiveSession::where('session_token', $sessionToken)
                ->where('live_class_id', $classId)
                ->where('is_active', true)
                ->first();

            if ($session && $session->isLeaseValid() && $session->accessCode?->isValidForLogin() && $session->accessCode->role === 'moderator') {
                return [
                    'allowed' => true,
                    'type' => 'access_code',
                    'id' => $session->accessCode->id,
                    'name' => $session->accessCode->student_identifier ?: 'Moderador',
                    'role' => 'moderator',
                ];
            }

            return ['allowed' => false];
        }

        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->isHost() && ($user->isAdmin() || ($liveClass && $liveClass->host_id === $user->id))) {
            return [
                'allowed' => true,
                'type' => 'user',
                'id' => $user->id,
                'name' => $user->name,
                'role' => 'host',
            ];
        }

        return ['allowed' => false];
    }

    protected function sessionToken(Request $request): ?string
    {
        return $request->header('X-Session-Token') ?: $request->input('session_token');
    }
}
