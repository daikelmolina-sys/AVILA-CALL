<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\ChatMessage;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_view_or_intercept_another_class_chat()
    {
        $host = User::factory()->create(['role' => 'host']);

        $classA = LiveClass::create([
            'title' => 'Clase A - Criptomonedas',
            'host_id' => $host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        $classB = LiveClass::create([
            'title' => 'Clase B - Acciones NYSE',
            'host_id' => $host->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        // Secret message in Class B
        ChatMessage::create([
            'live_class_id' => $classB->id,
            'sender_name' => 'Profesor NYSE',
            'sender_role' => 'host',
            'message' => 'Contenido confidencial Clase B',
        ]);

        // Student registers only in Class A
        $resA = AccessCode::createSecureCode($classA->id, 'Alumno Clase A');
        $loginA = $this->postJson(route('student.auth'), ['access_code' => $resA['raw_code']]);
        $tokenA = $loginA->json('session_token');

        // Student tries to fetch messages from Class B using Class A session token
        $response = $this->withHeader('X-Session-Token', $tokenA)
            ->getJson(route('api.chat.messages', $classB->id));

        // Must be rejected with 401 Unauthorized
        $response->assertStatus(401);

        // Student tries to access Class B room directly
        $roomResponse = $this->withHeader('X-Session-Token', $tokenA)
            ->get(route('student.room', $classB->id));

        // Must redirect back to Class A room
        $roomResponse->assertRedirect(route('student.room', $classA->id));
    }

    public function test_authenticated_host_cannot_read_or_post_in_another_hosts_chat(): void
    {
        $owner = User::factory()->create(['role' => 'host']);
        $foreignHost = User::factory()->create(['role' => 'host']);
        $liveClass = LiveClass::create([
            'title' => 'Clase privada del propietario',
            'host_id' => $owner->id,
            'status' => 'live',
            'chat_enabled' => true,
        ]);

        $this->actingAs($foreignHost)
            ->getJson(route('api.chat.messages', $liveClass))
            ->assertForbidden();

        $this->actingAs($foreignHost)
            ->postJson(route('api.chat.send', $liveClass), ['message' => 'Intrusion'])
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_messages', ['message' => 'Intrusion']);
    }
}
