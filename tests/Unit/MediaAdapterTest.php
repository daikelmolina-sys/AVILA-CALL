<?php

namespace Tests\Unit;

use App\Models\LiveClass;
use App\Models\User;
use App\Services\Media\LiveKitMediaAdapter;
use App\Services\Media\LocalWebRtcMediaAdapter;
use App\Services\Media\MediaManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MediaAdapterTest extends TestCase
{
    public function test_recording_is_strictly_disabled_on_all_adapters()
    {
        $local = new LocalWebRtcMediaAdapter();
        $this->assertTrue($local->isRecordingDisabled());

        $livekit = new LiveKitMediaAdapter();
        $this->assertTrue($livekit->isRecordingDisabled());
    }

    public function test_host_client_config_has_screen_and_audio_but_no_camera()
    {
        $local = new LocalWebRtcMediaAdapter();
        $class = new LiveClass(['id' => 42, 'stream_key' => 'key_42']);

        $config = $local->getClientConfig($class, 'host');

        $this->assertTrue($config['is_publisher']);
        $this->assertTrue($config['can_publish_screen']);
        $this->assertTrue($config['can_publish_audio']);
        $this->assertFalse($config['can_publish_camera']); // Camera strictly forbidden
        $this->assertFalse($config['recording_enabled']);
    }

    public function test_student_client_config_is_strictly_subscriber_without_publish_rights()
    {
        $local = new LocalWebRtcMediaAdapter();
        $class = new LiveClass(['id' => 42, 'stream_key' => 'key_42']);

        $config = $local->getClientConfig($class, 'student');

        $this->assertFalse($config['is_publisher']);
        $this->assertFalse($config['can_publish_screen']);
        $this->assertFalse($config['can_publish_audio']);
        $this->assertFalse($config['can_publish_camera']);
        $this->assertTrue($config['can_subscribe']);
    }

    public function test_livekit_is_unavailable_when_credentials_are_missing(): void
    {
        Config::set('services.livekit', [
            'host' => null,
            'api_key' => null,
            'api_secret' => null,
            'token_ttl' => 600,
        ]);

        $this->assertFalse((new LiveKitMediaAdapter())->isConfigured());
    }

    public function test_livekit_tokens_enforce_host_and_student_media_permissions(): void
    {
        Config::set('services.livekit', [
            'host' => 'wss://livekit.example.test',
            'api_key' => 'test-api-key',
            'api_secret' => 'test-api-secret',
            'token_ttl' => 600,
        ]);
        $adapter = new LiveKitMediaAdapter();
        $class = new LiveClass();
        $class->id = 42;
        $class->host_id = 7;
        $class->stream_key = 'avila_class_42';

        $host = $adapter->getClientConfig($class, 'host', 'host_7');
        $student = $adapter->getClientConfig($class, 'student', 'student_99');
        $hostClaims = $this->decodeJwtPayload($host['token']);
        $studentClaims = $this->decodeJwtPayload($student['token']);
        [$header, $payload, $signature] = explode('.', $student['token']);
        $expectedSignature = rtrim(strtr(base64_encode(
            hash_hmac('sha256', $header.'.'.$payload, 'test-api-secret', true)
        ), '+/', '-_'), '=');

        $this->assertTrue($adapter->isConfigured());
        $this->assertSame('test-api-key', $hostClaims['iss']);
        $this->assertSame('avila_class_42', $hostClaims['video']['room']);
        $this->assertTrue($hostClaims['video']['canPublish']);
        $this->assertSame(
            ['microphone', 'screen_share', 'screen_share_audio'],
            $hostClaims['video']['canPublishSources'],
        );
        $this->assertNotContains('camera', $hostClaims['video']['canPublishSources']);
        $this->assertFalse($studentClaims['video']['canPublish']);
        $this->assertFalse($studentClaims['video']['canPublishData']);
        $this->assertTrue($studentClaims['video']['canSubscribe']);
        $this->assertSame('student_99', $studentClaims['sub']);
        $this->assertTrue(hash_equals($expectedSignature, $signature));
        $this->assertLessThanOrEqual(605, $studentClaims['exp'] - time());
    }

    protected function decodeJwtPayload(string $token): array
    {
        $payload = explode('.', $token)[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    }
}
