<?php

namespace App\Services\Media;

use JsonException;

class LiveKitTokenFactory
{
    /**
     * Create a short-lived LiveKit join token without exposing the API secret.
     *
     * @throws JsonException
     */
    public function create(
        string $apiKey,
        string $apiSecret,
        string $roomName,
        string $identity,
        string $role,
        int $ttlSeconds = 600,
    ): string {
        $isHost = $role === 'host';
        $now = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'nbf' => $now - 5,
            'exp' => $now + max(60, $ttlSeconds),
            'name' => $isHost ? 'Profesor' : 'Alumno',
            'metadata' => json_encode(['role' => $role], JSON_THROW_ON_ERROR),
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => $isHost,
                'canSubscribe' => true,
                'canPublishData' => false,
                'canUpdateOwnMetadata' => false,
                'hidden' => !$isHost,
                'canPublishSources' => $isHost
                    ? ['microphone', 'screen_share', 'screen_share_audio']
                    : [],
            ],
        ];

        return $this->encode($header, $payload, $apiSecret);
    }

    public function createRoomAdmin(string $apiKey, string $apiSecret, string $roomName): string
    {
        return $this->createServiceToken($apiKey, $apiSecret, [
            'room' => $roomName,
            'roomAdmin' => true,
        ]);
    }

    public function createRoomCreateToken(string $apiKey, string $apiSecret): string
    {
        return $this->createServiceToken($apiKey, $apiSecret, ['roomCreate' => true]);
    }

    protected function createServiceToken(string $apiKey, string $apiSecret, array $videoGrant): string
    {
        $now = time();

        return $this->encode(
            ['alg' => 'HS256', 'typ' => 'JWT'],
            [
                'iss' => $apiKey,
                'sub' => 'avila-call-backend',
                'nbf' => $now - 5,
                'exp' => $now + 60,
                'video' => $videoGrant,
            ],
            $apiSecret,
        );
    }

    protected function encode(array $header, array $payload, string $apiSecret): string
    {
        $segments = [
            $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $apiSecret, true);
        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
