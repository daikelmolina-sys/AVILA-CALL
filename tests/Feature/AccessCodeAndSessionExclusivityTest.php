<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Models\User;
use App\Services\Session\ClassSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessCodeAndSessionExclusivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected LiveClass $liveClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create([
            'role' => 'host',
        ]);

        $this->liveClass = LiveClass::create([
            'title' => 'Test Trading Class',
            'host_id' => $this->host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);
    }

    public function test_valid_access_code_authenticates_student()
    {
        $codeData = AccessCode::createSecureCode($this->liveClass->id, 'Juan Perez');
        $rawCode = $codeData['raw_code'];

        $response = $this->postJson(route('student.auth'), [
            'access_code' => $rawCode,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'role' => 'student',
        ]);

        $sessionToken = $response->json('session_token');
        $this->assertNotEmpty($sessionToken);

        $session = ActiveSession::where('session_token', $sessionToken)->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertEquals($this->liveClass->id, $session->live_class_id);
    }

    public function test_invalid_code_fails_with_generic_message()
    {
        $response = $this->postJson(route('student.auth'), [
            'access_code' => 'AC-FAKE-CODE-9999',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 'INVALID_CODE',
        ]);
    }

    public function test_revoked_code_cannot_authenticate()
    {
        $codeData = AccessCode::createSecureCode($this->liveClass->id, 'Revoked User');
        $rawCode = $codeData['raw_code'];
        $code = $codeData['access_code'];

        $code->update(['status' => 'revoked', 'revoked_at' => now()]);

        $response = $this->postJson(route('student.auth'), [
            'access_code' => $rawCode,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 'CODE_INACTIVE',
        ]);
    }

    public function test_single_session_exclusivity_second_device_atomically_invalidates_first()
    {
        $codeData = AccessCode::createSecureCode($this->liveClass->id, 'Maria Solo');
        $rawCode = $codeData['raw_code'];
        $accessCode = $codeData['access_code'];

        // 1. First device enters
        $responseDev1 = $this->postJson(route('student.auth'), [
            'access_code' => $rawCode,
        ]);
        $responseDev1->assertStatus(200);
        $tokenDev1 = $responseDev1->json('session_token');

        // Check Device 1 is active
        $sessionDev1 = ActiveSession::where('session_token', $tokenDev1)->first();
        $this->assertTrue($sessionDev1->is_active);

        // 2. Second device enters with the EXACT same code
        $responseDev2 = $this->postJson(route('student.auth'), [
            'access_code' => $rawCode,
        ]);
        $responseDev2->assertStatus(200);
        $tokenDev2 = $responseDev2->json('session_token');

        $this->assertNotEquals($tokenDev1, $tokenDev2);

        // Check Device 1 was atomically invalidated
        $sessionDev1->refresh();
        $this->assertFalse($sessionDev1->is_active);
        $this->assertEquals('replaced_by_new_device', $sessionDev1->revocation_reason);

        // Check Device 2 is the ONLY active session
        $sessionDev2 = ActiveSession::where('session_token', $tokenDev2)->first();
        $this->assertTrue($sessionDev2->is_active);

        // Verify total active sessions for this code is strictly 1
        $activeCount = ActiveSession::where('access_code_id', $accessCode->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(1, $activeCount);

        // 3. Heartbeat on Device 1 must return 'revoked'
        $heartbeatDev1 = $this->postJson(route('student.heartbeat'), [
            'session_token' => $tokenDev1,
        ]);
        $heartbeatDev1->assertStatus(401);
        $heartbeatDev1->assertJson([
            'status' => 'revoked',
            'reason' => 'replaced_by_new_device',
        ]);

        // 4. Heartbeat on Device 2 must succeed and renew lease
        $heartbeatDev2 = $this->postJson(route('student.heartbeat'), [
            'session_token' => $tokenDev2,
        ]);
        $heartbeatDev2->assertStatus(200);
        $heartbeatDev2->assertJson([
            'status' => 'active',
            'class_status' => 'live',
        ]);
    }

    public function test_multiple_successive_logins_strictly_yield_one_active_session()
    {
        $codeData = AccessCode::createSecureCode($this->liveClass->id, 'Stress Student');
        $rawCode = $codeData['raw_code'];
        $code = $codeData['access_code'];

        $lastToken = null;
        for ($i = 0; $i < 20; $i++) {
            $resp = $this->postJson(route('student.auth'), [
                'access_code' => $rawCode,
            ]);
            $resp->assertStatus(200);
            $lastToken = $resp->json('session_token');
        }

        // Must have exactly ONE active session
        $activeSessions = ActiveSession::where('access_code_id', $code->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(1, $activeSessions);
        $this->assertEquals($lastToken, $activeSessions->first()->session_token);
    }

    public function test_replaced_livekit_session_is_removed_from_sfu(): void
    {
        Config::set('services.livekit', [
            'host' => 'wss://livekit.example.test',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'token_ttl' => 600,
        ]);
        Http::fake([
            'https://livekit.example.test/*' => Http::response([], 200),
        ]);
        $this->liveClass->update([
            'media_provider' => 'livekit_sfu',
            'stream_key' => 'avila_class_'.$this->liveClass->id,
        ]);
        $codeData = AccessCode::createSecureCode($this->liveClass->id, 'Alumno SFU');

        $first = $this->postJson(route('student.auth'), ['access_code' => $codeData['raw_code']]);
        $firstSession = ActiveSession::where('session_token', $first->json('session_token'))->firstOrFail();
        $this->postJson(route('student.auth'), ['access_code' => $codeData['raw_code']])->assertOk();

        Http::assertSent(function (Request $request) use ($firstSession) {
            return $request->url() === 'https://livekit.example.test/twirp/livekit.RoomService/RemoveParticipant'
                && $request['room'] === 'avila_class_'.$this->liveClass->id
                && $request['identity'] === 'student_'.$firstSession->id
                && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
        });
    }
}
