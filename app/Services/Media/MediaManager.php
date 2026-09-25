<?php

namespace App\Services\Media;

use App\Models\LiveClass;
use InvalidArgumentException;

class MediaManager
{
    protected array $adapters = [];

    public function __construct()
    {
        $this->adapters['local_webrtc'] = new LocalWebRtcMediaAdapter();
        $this->adapters['livekit_sfu'] = new LiveKitMediaAdapter();
    }

    public function getAdapter(?string $name = null): MediaAdapterInterface
    {
        $provider = $name ?: config('services.media.default', env('MEDIA_PROVIDER', 'local_webrtc'));

        if (!isset($this->adapters[$provider])) {
            return $this->adapters['local_webrtc'];
        }

        return $this->adapters[$provider];
    }

    public function getAdapterForClass(LiveClass $class): MediaAdapterInterface
    {
        return $this->getAdapter($class->media_provider);
    }
}
