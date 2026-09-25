<?php

namespace App\Services\Media;

use App\Models\LiveClass;
use RuntimeException;

class LiveKitMediaAdapter implements MediaAdapterInterface
{
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $livekitHost;
    protected int $tokenTtl;

    public function __construct(protected ?LiveKitTokenFactory $tokenFactory = null)
    {
        $this->tokenFactory ??= new LiveKitTokenFactory();
        $this->apiKey = config('services.livekit.api_key');
        $this->apiSecret = config('services.livekit.api_secret');
        $this->livekitHost = config('services.livekit.host');
        $this->tokenTtl = (int) config('services.livekit.token_ttl', 600);
    }

    public function getProviderName(): string
    {
        return 'livekit_sfu';
    }

    public function isConfigured(): bool
    {
        if (!$this->apiKey || !$this->apiSecret || !$this->livekitHost) {
            return false;
        }

        return in_array(parse_url($this->livekitHost, PHP_URL_SCHEME), ['ws', 'wss'], true);
    }

    public function initializeClassStream(LiveClass $class): array
    {
        $this->ensureConfigured();
        $roomName = 'avila_class_'.$class->id;
        $class->update([
            'media_provider' => $this->getProviderName(),
            'stream_key' => $roomName,
        ]);

        return [
            'provider' => $this->getProviderName(),
            'room_name' => $roomName,
            'host_url' => $this->livekitHost,
            'screen_share' => true,
            'mic_audio' => true,
            'camera_enabled' => false,
            'recording_enabled' => false,
        ];
    }

    public function endClassStream(LiveClass $class): void
    {
        $class->update(['stream_key' => null]);
    }

    public function getClientConfig(LiveClass $class, string $role, ?string $participantId = null): array
    {
        $this->ensureConfigured();
        $isPublisher = $role === 'host';
        $roomName = $class->stream_key ?: ('avila_class_'.$class->id);
        $identity = $participantId ?: ($isPublisher ? 'host_'.$class->host_id : 'student_unknown');

        return [
            'provider' => $this->getProviderName(),
            'host_url' => $this->livekitHost,
            'room_name' => $roomName,
            'participant_id' => $identity,
            'token' => $this->tokenFactory->create(
                (string) $this->apiKey,
                (string) $this->apiSecret,
                $roomName,
                $identity,
                $isPublisher ? 'host' : 'student',
                $this->tokenTtl,
            ),
            'is_publisher' => $isPublisher,
            'permissions' => [
                'can_publish' => $isPublisher,
                'can_publish_data' => false,
                'can_publish_sources' => $isPublisher
                    ? ['microphone', 'screen_share', 'screen_share_audio']
                    : [],
                'can_subscribe' => true,
            ],
            'recording_enabled' => false,
        ];
    }

    public function getMaxViewerCapacity(): int
    {
        // This is an architectural target, not verified capacity for a deployment.
        return $this->isConfigured() ? 5000 : 0;
    }

    public function isRecordingDisabled(): bool
    {
        return true;
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('LiveKit no esta configurado. Define LIVEKIT_HOST, LIVEKIT_API_KEY y LIVEKIT_API_SECRET.');
        }
    }
}
