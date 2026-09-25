<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_host_or_admin_can_enter_dashboard(): void
    {
        foreach (['viewer', 'student', 'moderator'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.classes.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.classes.store'), ['title' => 'Clase no autorizada '.$role])
                ->assertForbidden();
        }

        $this->assertSame(0, LiveClass::count());
    }

    public function test_professor_creates_a_class_owned_by_themself(): void
    {
        $host = User::factory()->create(['role' => 'host']);

        $response = $this->actingAs($host)->post(route('admin.classes.store'), [
            'title' => 'Clase del profesor',
            'media_provider' => 'local_webrtc',
        ]);

        $liveClass = LiveClass::where('title', 'Clase del profesor')->firstOrFail();
        $response->assertRedirect(route('admin.classes.show', $liveClass));
        $this->assertSame($host->id, $liveClass->host_id);
    }

    public function test_livekit_class_cannot_be_created_without_sfu_configuration(): void
    {
        Config::set('services.livekit', [
            'host' => null,
            'api_key' => null,
            'api_secret' => null,
            'token_ttl' => 600,
        ]);
        $host = User::factory()->create(['role' => 'host']);

        $this->actingAs($host)->post(route('admin.classes.store'), [
            'title' => 'Clase SFU sin configurar',
            'media_provider' => 'livekit_sfu',
        ])->assertSessionHasErrors('media_provider');

        $this->assertDatabaseMissing('live_classes', ['title' => 'Clase SFU sin configurar']);
    }

    public function test_professor_only_generates_codes_for_own_classes_while_admin_can_manage_any(): void
    {
        $owner = User::factory()->create(['role' => 'host']);
        $otherHost = User::factory()->create(['role' => 'host']);
        $admin = User::factory()->create(['role' => 'admin']);
        $liveClass = LiveClass::create([
            'title' => 'Clase del propietario',
            'host_id' => $owner->id,
            'status' => 'scheduled',
            'chat_enabled' => true,
        ]);

        $this->actingAs($otherHost)
            ->postJson(route('admin.classes.codes.generate', $liveClass), [
                'quantity' => 1,
                'role' => 'student',
            ])
            ->assertForbidden();

        $this->assertSame(0, AccessCode::where('live_class_id', $liveClass->id)->count());

        $this->actingAs($admin)
            ->postJson(route('admin.classes.codes.generate', $liveClass), [
                'quantity' => 1,
                'role' => 'student',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1]);
    }

    public function test_non_host_credentials_cannot_start_a_dashboard_session(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'password' => 'password',
        ]);

        $this->post(route('host.login.submit'), [
            'email' => $viewer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
