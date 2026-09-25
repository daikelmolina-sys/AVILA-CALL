<?php

namespace App\Services\Media;

use App\Models\LiveClass;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LiveKitRoomService
{
    public function __construct(protected ?LiveKitTokenFactory $tokenFactory = null)
    {
        $this->tokenFactory ??= new LiveKitTokenFactory();
    }

    public function removeStudentSession(LiveClass $liveClass, int $sessionId): bool
    {
        if ($liveClass->media_provider !== 'livekit_sfu') {
            return true;
        }

        $room = $liveClass->stream_key ?: 'avila_class_'.$liveClass->id;
        $token = $this->tokenFactory->createRoomAdmin(
            (string) config('services.livekit.api_key'),
            (string) config('services.livekit.api_secret'),
            $room,
        );

        return $this->call(
            'RemoveParticipant',
            $token,
            [
                'room' => $room,
                'identity' => 'student_'.$sessionId,
                'revoke_token_ts' => time(),
            ],
        );
    }

    public function deleteClassRoom(LiveClass $liveClass): bool
    {
        if ($liveClass->media_provider !== 'livekit_sfu') {
            return true;
        }

        $room = $liveClass->stream_key ?: 'avila_class_'.$liveClass->id;
        $token = $this->tokenFactory->createRoomCreateToken(
            (string) config('services.livekit.api_key'),
            (string) config('services.livekit.api_secret'),
        );

        return $this->call('DeleteRoom', $token, ['room' => $room]);
    }

    protected function call(string $method, string $token, array $payload): bool
    {
        $host = (string) config('services.livekit.host');
        if (!$host || !config('services.livekit.api_key') || !config('services.livekit.api_secret')) {
            return false;
        }

        $baseUrl = preg_replace('#^wss://#', 'https://', rtrim($host, '/'));
        $baseUrl = preg_replace('#^ws://#', 'http://', $baseUrl);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->timeout(5)
                ->post($baseUrl.'/twirp/livekit.RoomService/'.$method, $payload);

            if ($response->successful() || $response->status() === 404) {
                return true;
            }

            Log::warning('LiveKit RoomService rechazo una operacion.', [
                'method' => $method,
                'status' => $response->status(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('LiveKit RoomService no estuvo disponible.', [
                'method' => $method,
                'exception' => $exception::class,
            ]);
        }

        return false;
    }
}
