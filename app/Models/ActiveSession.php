<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveSession extends Model
{
    use HasFactory;

    // Default lease duration in seconds (30s lease with 10s heartbeat from frontend)
    public const LEASE_SECONDS = 30;

    protected $fillable = [
        'access_code_id',
        'live_class_id',
        'session_token',
        'ip_address',
        'user_agent',
        'is_active',
        'revocation_reason',
        'lease_expires_at',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lease_expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }

    public function isLeaseValid(): bool
    {
        return $this->is_active && $this->lease_expires_at->isFuture();
    }
}
