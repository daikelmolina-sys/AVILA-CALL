<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add role to users table if not exists
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 32)->default('host')->after('email'); // admin, host
            });
        }

        // Live Classes table
        Schema::create('live_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('scheduled'); // scheduled, live, ended
            $table->boolean('chat_enabled')->default(true);
            $table->string('media_provider', 64)->default('local_webrtc'); // local_webrtc, livekit, cloudflare
            $table->string('stream_key', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('current_viewers_count')->default(0);
            $table->unsignedInteger('peak_viewers_count')->default(0);
            $table->timestamps();
        });

        // Access Codes table
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->string('code_hash', 64)->index(); // SHA-256 for secure lookup
            $table->string('code_prefix', 16)->nullable(); // e.g. "AC-789" for admin preview without exposing full code
            $table->string('student_identifier')->nullable(); // Optional: name or email alias
            $table->string('status', 32)->default('available'); // available, connected, revoked, expired, banned
            $table->string('role', 32)->default('student'); // student, moderator
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['live_class_id', 'status']);
        });

        // Active Sessions table (single session exclusivity enforcement)
        Schema::create('active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_code_id')->constrained('access_codes')->cascadeOnDelete();
            $table->foreignId('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('revocation_reason', 64)->nullable(); // replaced_by_new_device, kicked_by_moderator, banned, class_ended, manual_logout
            $table->timestamp('lease_expires_at')->index();
            $table->timestamp('last_heartbeat_at');
            $table->timestamps();

            $table->index(['access_code_id', 'is_active']);
        });

        // Chat Messages table
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->foreignId('access_code_id')->nullable()->constrained('access_codes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name', 120);
            $table->string('sender_role', 32); // host, moderator, student
            $table->text('message');
            $table->boolean('is_deleted')->default(false);
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_class_id', 'id']);
        });

        // Moderation Logs table
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->string('actor_type', 32); // user, access_code
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name', 120);
            $table->string('action', 64); // toggle_chat, delete_message, mute_student, kick_student, ban_code, promote_moderator, demote_moderator
            $table->string('target_type', 32)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name', 120)->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['live_class_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('active_sessions');
        Schema::dropIfExists('access_codes');
        Schema::dropIfExists('live_classes');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
