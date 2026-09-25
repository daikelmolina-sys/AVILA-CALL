<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'host_id',
        'status', // scheduled, live, ended
        'chat_enabled',
        'media_provider',
        'stream_key',
        'started_at',
        'ended_at',
        'current_viewers_count',
        'peak_viewers_count',
    ];

    protected function casts(): array
    {
        return [
            'chat_enabled' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'current_viewers_count' => 'integer',
            'peak_viewers_count' => 'integer',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }
}
