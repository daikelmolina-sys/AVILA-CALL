<?php

namespace App\Services\Media;

use App\Models\LiveClass;
use Illuminate\Support\Str;

class LocalWebRtcMediaAdapter implements MediaAdapterInterface
{
    public function getProviderName(): string
    {
        return 'local_webrtc';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function initializeClassStream(LiveClass $class): array
    {
        $streamKey = 'stream_' . $class->id . '_' . Str::random(16);
        $class->update([
            'media_provider' => $this->getProviderName(),
            'stream_key' => $streamKey,
        ]);

        return [
            'provider' => $this->getProviderName(),
            'stream_key' => $streamKey,
            'screen_share' => true,
            'mic_audio' => true,
            'camera_enabled' => false, // strictly disabled by product decision
            'recording_enabled' => false, // strictly disabled
        ];
    }

    public function endClassStream(LiveClass $class): void
    {
        $class->update([
            'stream_key' => null,
        ]);
    }

    public function getClientConfig(LiveClass $class, string $role, ?string $participantId = null): array
    {
        $isPublisher = ($role === 'host');

        return [
            'provider' => $this->getProviderName(),
            'room_id' => 'avila_class_' . $class->id,
            'stream_key' => $class->stream_key,
            'is_publisher' => $isPublisher,
            'can_publish_screen' => $isPublisher,
            'can_publish_audio' => $isPublisher,
            'can_publish_camera' => false, // camera forbidden by default
            'can_subscribe' => true,
            'recording_enabled' => false,
            'signaling_channel' => 'avila-call-class-' . $class->id,
        ];
    }

    public function getMaxViewerCapacity(): int
    {
        // Local browser peer-to-peer / broadcast adapter is intended for development and demo
        return 50;
    }

    public function isRecordingDisabled(): bool
    {
        return true;
    }
}
