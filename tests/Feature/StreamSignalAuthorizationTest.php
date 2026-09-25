<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StreamSignalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected LiveClass $liveClass;
    protected string $studentToken;
    protected ActiveSession $studentSession;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->host = User::factory()->create(['role' => 'host']);
        $this->liveClass = LiveClass::create([
            'title' => 'Clase WebRTC segura',
            'host_id' => $this->host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        $generated = AccessCode::createSecureCode($this->liveClass->id, 'Alumno WebRTC');
        $login = $this->postJson(route('student.auth'), ['access_code' => $generated['raw_code']]);
        $this->studentToken = $login->json('session_token');
        $this->studentSession = ActiveSession::where('session_token', $this->studentToken)->firstOrFail();
    }

    public function test_unauthenticated_users_cannot_send_or_read_signals(): void
    {
        $this->postJson(route('api.stream.signal.send', $this->liveClass), [
            'from_peer_id' => 'host',
            'target_peer_id' => 'all',
            'type' => 'stream_started',
        ])->assertUnauthorized();

        $this->getJson(route('api.stream.signal.get', $this->liveClass).'?peer_id=host')
            ->assertUnauthorized();
    }

    public function test_foreign_host_cannot_access_another_class_signaling(): void
    {
        $foreignHost = User::factory()->create(['role' => 'host']);

        $this->actingAs($foreignHost)->postJson(route('api.stream.signal.send', $this->liveClass), [
            'from_peer_id' => 'host',
            'target_peer_id' => 'all',
            'type' => 'stream_started',
        ])->assertForbidden();
    }

    public function test_student_identity_is_bound_to_active_session_and_class(): void
    {
        $peerId = 'student_'.$this->studentSession->id;

        $this->withHeader('X-Session-Token', $this->studentToken)
            ->postJson(route('api.stream.signal.send', $this->liveClass), [
                'from_peer_id' => $peerId,
                'target_peer_id' => 'host',
                'type' => 'request_stream',
            ])->assertOk();

        $hostSignals = $this->withHeader('X-Session-Token', '')
            ->actingAs($this->host)
            ->getJson(route('api.stream.signal.get', $this->liveClass).'?peer_id=host');

        $hostSignals->assertOk()->assertJsonPath('signals.0.from_peer_id', $peerId);

        $this->withHeader('X-Session-Token', $this->studentToken)
            ->postJson(route('api.stream.signal.send', $this->liveClass), [
                'from_peer_id' => 'student_999999',
                'target_peer_id' => 'host',
                'type' => 'request_stream',
            ])->assertForbidden();
    }

    public function test_student_token_cannot_signal_in_another_class(): void
    {
        $otherClass = LiveClass::create([
            'title' => 'Otra clase',
            'host_id' => $this->host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        $this->withHeader('X-Session-Token', $this->studentToken)
            ->postJson(route('api.stream.signal.send', $otherClass), [
                'from_peer_id' => 'student_'.$this->studentSession->id,
                'target_peer_id' => 'host',
                'type' => 'request_stream',
            ])->assertUnauthorized();
    }

    public function test_host_offer_can_only_be_read_by_its_intended_active_student(): void
    {
        $peerId = 'student_'.$this->studentSession->id;

        $this->actingAs($this->host)->postJson(route('api.stream.signal.send', $this->liveClass), [
            'from_peer_id' => 'host',
            'target_peer_id' => $peerId,
            'type' => 'stream_offer',
            'payload' => ['type' => 'offer', 'sdp' => 'test-sdp'],
        ])->assertOk();

        $this->withHeader('X-Session-Token', $this->studentToken)
            ->getJson(route('api.stream.signal.get', $this->liveClass).'?peer_id='.$peerId)
            ->assertOk()
            ->assertJsonPath('signals.0.type', 'stream_offer');
    }

    public function test_revoked_student_session_cannot_signal_even_with_host_cookie_present(): void
    {
        $this->studentSession->update(['is_active' => false, 'revocation_reason' => 'revoked']);

        $this->actingAs($this->host)
            ->withHeader('X-Session-Token', $this->studentToken)
            ->postJson(route('api.stream.signal.send', $this->liveClass), [
                'from_peer_id' => 'host',
                'target_peer_id' => 'all',
                'type' => 'stream_started',
            ])->assertUnauthorized();
    }

    public function test_signals_contain_uuid_and_respect_since_filter(): void
    {
        $peerId = 'student_'.$this->studentSession->id;

        $this->actingAs($this->host)->postJson(route('api.stream.signal.send', $this->liveClass), [
            'from_peer_id' => 'host',
            'target_peer_id' => 'all',
            'type' => 'stream_started',
        ])->assertOk();

        $response = $this->withHeader('X-Session-Token', $this->studentToken)
            ->getJson(route('api.stream.signal.get', $this->liveClass).'?peer_id='.$peerId);

        $response->assertOk();
        $signals = $response->json('signals');
        $this->assertNotEmpty($signals);
        $this->assertArrayHasKey('id', $signals[0]);
        $this->assertArrayHasKey('timestamp', $signals[0]);

        // When requesting since future timestamp, broadcast is excluded
        $future = microtime(true) + 10;
        $responseFiltered = $this->withHeader('X-Session-Token', $this->studentToken)
            ->getJson(route('api.stream.signal.get', $this->liveClass).'?peer_id='.$peerId.'&since='.$future);
        $this->assertEmpty($responseFiltered->json('signals'));
    }
}
