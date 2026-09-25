<?php

namespace App\Services\Media;

use App\Models\LiveClass;

interface MediaAdapterInterface
{
    /**
     * Unique identifier for the media provider
     */
    public function getProviderName(): string;

    /**
     * Whether this adapter has all configuration required to operate.
     */
    public function isConfigured(): bool;

    /**
     * Initializes stream credentials/room for a class
     */
    public function initializeClassStream(LiveClass $class): array;

    /**
     * Terminate the class stream and release resources
     */
    public function endClassStream(LiveClass $class): void;

    /**
     * Generate client configuration for viewing or broadcasting
     */
    public function getClientConfig(LiveClass $class, string $role, ?string $participantId = null): array;

    /**
     * Maximum theoretical viewers capacity for this adapter
     */
    public function getMaxViewerCapacity(): int;

    /**
     * Guarantee that recording is disabled
     */
    public function isRecordingDisabled(): bool;
}
