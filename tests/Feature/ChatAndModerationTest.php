<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\ChatMessage;
use App\Models\LiveClass;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAndModerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected LiveClass $liveClass;
    protected string $studentToken;
    protected AccessCode $studentCode;
    protected string $modToken;
    protected AccessCode $modCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['role' => 'host']);
        $this->liveClass = LiveClass::create([
            'title' => 'Forex Live Trading',
            'host_id' => $this->host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        // Student auth
        $studentRes = AccessCode::createSecureCode($this->liveClass->id, 'Alumno Uno', 'student');
        $this->studentCode = $studentRes['access_code'];
        $sLogin = $this->postJson(route('student.auth'), ['access_code' => $studentRes['raw_code']]);
        $this->studentToken = $sLogin->json('session_token');

        // Moderator auth
        $modRes = AccessCode::createSecureCode($this->liveClass->id, 'Mod Uno', 'moderator');
        $this->modCode = $modRes['access_code'];
        $mLogin = $this->postJson(route('student.auth'), ['access_code' => $modRes['raw_code']]);
        $this->modToken = $mLogin->json('session_token');
    }

    public function test_student_can_send_message_when_chat_is_enabled()
    {
        $response = $this->postJson(route('api.chat.send', $this->liveClass->id), [
            'message' => '¿Cuál es el stop loss recomendado?',
            'session_token' => $this->studentToken,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => [
                'sender_name' => 'Alumno Uno',
                'sender_role' => 'student',
            ],
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'live_class_id' => $this->liveClass->id,
            'message' => '¿Cuál es el stop loss recomendado?',
        ]);
    }

    public function test_student_token_takes_precedence_over_ambient_host_auth_session()
    {
        // When testing in the same browser, the host cookie is present, but the student session_token must take priority
        $response = $this->actingAs($this->host)
            ->postJson(route('api.chat.send', $this->liveClass->id), [
                'message' => 'Pregunta de alumno desde pestaña compartida',
                'session_token' => $this->studentToken,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => [
                'sender_name' => 'Alumno Uno',
                'sender_role' => 'student',
            ],
        ]);
    }

    public function test_student_cannot_send_message_when_chat_is_disabled()
    {
        // Host closes chat
        $this->liveClass->update(['chat_enabled' => false]);

        $response = $this->postJson(route('api.chat.send', $this->liveClass->id), [
            'message' => 'Mensaje bloqueado',
            'session_token' => $this->studentToken,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 'CHAT_DISABLED',
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'message' => 'Mensaje bloqueado',
        ]);
    }

    public function test_host_can_send_message_even_when_chat_is_closed()
    {
        $this->liveClass->update(['chat_enabled' => false]);

        $response = $this->actingAs($this->host)
            ->postJson(route('api.chat.send', $this->liveClass->id), [
                'message' => 'Aviso: Presten atención a la ruptura de soporte.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'message' => 'Aviso: Presten atención a la ruptura de soporte.',
            'sender_role' => 'host',
        ]);
    }

    public function test_message_tags_are_stripped_and_escaped_preventing_xss()
    {
        $malicious = '<script>alert("hacked")</script><b>Texto Seguro</b>';

        $response = $this->postJson(route('api.chat.send', $this->liveClass->id), [
            'message' => $malicious,
            'session_token' => $this->studentToken,
        ]);

        $response->assertStatus(201);
        $msg = ChatMessage::where('live_class_id', $this->liveClass->id)->latest()->first();

        $this->assertStringNotContainsString('<script>', $msg->message);
        $this->assertEquals('Texto Seguro', $msg->message);
    }

    public function test_moderator_can_delete_message_but_student_cannot()
    {
        $msg = ChatMessage::create([
            'live_class_id' => $this->liveClass->id,
            'sender_name' => 'Spammer',
            'sender_role' => 'student',
            'message' => 'Spam link',
        ]);

        // Student attempt: forbidden
        $studentAttempt = $this->postJson(route('api.chat.delete', $this->liveClass->id), [
            'message_id' => $msg->id,
            'session_token' => $this->studentToken,
        ]);
        $studentAttempt->assertStatus(403);

        // Moderator attempt: allowed
        $modAttempt = $this->postJson(route('api.chat.delete', $this->liveClass->id), [
            'message_id' => $msg->id,
            'session_token' => $this->modToken,
        ]);
        $modAttempt->assertStatus(200);
        $modAttempt->assertJson(['success' => true]);

        $msg->refresh();
        $this->assertTrue($msg->is_deleted);
    }

    public function test_student_token_cannot_inherit_moderation_from_ambient_host_cookie(): void
    {
        $msg = ChatMessage::create([
            'live_class_id' => $this->liveClass->id,
            'sender_name' => 'Mensaje protegido',
            'sender_role' => 'student',
            'message' => 'No debe borrarse',
        ]);

        $this->actingAs($this->host)
            ->postJson(route('api.chat.delete', $this->liveClass->id), [
                'message_id' => $msg->id,
                'session_token' => $this->studentToken,
            ])->assertForbidden();

        $this->assertFalse($msg->fresh()->is_deleted);
    }

    public function test_host_can_kick_student_and_terminate_session()
    {
        $response = $this->actingAs($this->host)
            ->postJson(route('api.chat.kick', $this->liveClass->id), [
                'access_code_id' => $this->studentCode->id,
                'reason' => 'Comportamiento no adecuado',
            ]);

        $response->assertStatus(200);

        // Student heartbeat must now return revoked
        $heartbeat = $this->postJson(route('student.heartbeat'), [
            'session_token' => $this->studentToken,
        ]);
        $heartbeat->assertStatus(401);
        $heartbeat->assertJson([
            'status' => 'revoked',
            'reason' => 'kicked_by_moderator',
        ]);
    }

    public function test_host_can_ban_student_code_permanently()
    {
        $response = $this->actingAs($this->host)
            ->postJson(route('api.chat.ban', $this->liveClass->id), [
                'access_code_id' => $this->studentCode->id,
                'reason' => 'Violación grave de normas',
            ]);

        $response->assertStatus(200);

        $this->studentCode->refresh();
        $this->assertEquals('banned', $this->studentCode->status);

        // Heartbeat revoked
        $heartbeat = $this->postJson(route('student.heartbeat'), [
            'session_token' => $this->studentToken,
        ]);
        $heartbeat->assertStatus(401);
        $heartbeat->assertJson(['status' => 'revoked', 'reason' => 'banned']);
    }

    public function test_purge_command_removes_messages_older_than_30_days_and_retains_recent()
    {
        // 1. Message from 40 days ago
        $oldMsg = ChatMessage::create([
            'live_class_id' => $this->liveClass->id,
            'sender_name' => 'Alumno Antiguo',
            'sender_role' => 'student',
            'message' => 'Mensaje de hace 40 días',
        ]);
        ChatMessage::where('id', $oldMsg->id)->update(['created_at' => now()->subDays(40)]);

        // 2. Message from 5 days ago
        $recentMsg = ChatMessage::create([
            'live_class_id' => $this->liveClass->id,
            'sender_name' => 'Alumno Reciente',
            'sender_role' => 'student',
            'message' => 'Mensaje de hace 5 días',
        ]);
        ChatMessage::where('id', $recentMsg->id)->update(['created_at' => now()->subDays(5)]);

        // Execute purge command
        $this->artisan('avila:purge-chat', ['--days' => 30])
            ->assertSuccessful();

        // Old message must be gone
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $oldMsg->id,
        ]);

        // Recent message must be preserved
        $this->assertDatabaseHas('chat_messages', [
            'id' => $recentMsg->id,
        ]);
    }

    public function test_chat_and_audit_are_purged_one_hour_after_class_ends()
    {
        $eligibleClass = LiveClass::create([
            'title' => 'Clase finalizada hace más de una hora',
            'host_id' => $this->host->id,
            'status' => 'ended',
            'chat_enabled' => false,
            'ended_at' => now()->subMinutes(61),
        ]);

        $recentlyEndedClass = LiveClass::create([
            'title' => 'Clase finalizada recientemente',
            'host_id' => $this->host->id,
            'status' => 'ended',
            'chat_enabled' => false,
            'ended_at' => now()->subMinutes(59),
        ]);

        $eligibleMessage = ChatMessage::create([
            'live_class_id' => $eligibleClass->id,
            'sender_name' => 'Alumno para purga',
            'sender_role' => 'student',
            'message' => 'Debe eliminarse después de una hora',
        ]);
        $recentMessage = ChatMessage::create([
            'live_class_id' => $recentlyEndedClass->id,
            'sender_name' => 'Alumno reciente',
            'sender_role' => 'student',
            'message' => 'Debe conservarse antes de una hora',
        ]);

        $eligibleLog = ModerationLog::create([
            'live_class_id' => $eligibleClass->id,
            'actor_type' => 'user',
            'actor_id' => $this->host->id,
            'actor_name' => $this->host->name,
            'action' => 'toggle_chat',
            'details' => 'Debe eliminarse después de una hora',
        ]);
        $recentLog = ModerationLog::create([
            'live_class_id' => $recentlyEndedClass->id,
            'actor_type' => 'user',
            'actor_id' => $this->host->id,
            'actor_name' => $this->host->name,
            'action' => 'toggle_chat',
            'details' => 'Debe conservarse antes de una hora',
        ]);

        $this->artisan('avila:purge-ended-class-data', ['--hours' => 1])
            ->assertSuccessful();

        $this->assertDatabaseMissing('chat_messages', ['id' => $eligibleMessage->id]);
        $this->assertDatabaseMissing('moderation_logs', ['id' => $eligibleLog->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $recentMessage->id]);
        $this->assertDatabaseHas('moderation_logs', ['id' => $recentLog->id]);
        $this->assertDatabaseHas('live_classes', ['id' => $eligibleClass->id]);
    }
}
