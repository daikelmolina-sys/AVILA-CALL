<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AccessCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_class_id',
        'code_hash',
        'code_prefix',
        'student_identifier',
        'status', // available, connected, revoked, expired, banned
        'role',   // student, moderator
        'revoked_at',
        'banned_at',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'banned_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class);
    }

    /**
     * Generate a cryptographically secure access code and return [raw_code, instance]
     */
    public static function createSecureCode(int $liveClassId, ?string $studentIdentifier = null, string $role = 'student', ?\DateTimeInterface $expiresAt = null): array
    {
        // 16-character alphanumeric uppercase grouped code: e.g. "AC-7K9M-4W2X-8QJP"
        $token = strtoupper(Str::random(12));
        $formattedCode = 'AC-' . substr($token, 0, 4) . '-' . substr($token, 4, 4) . '-' . substr($token, 8, 4);
        $codeHash = hash('sha256', $formattedCode);

        $instance = self::create([
            'live_class_id' => $liveClassId,
            'code_hash' => $codeHash,
            'code_prefix' => substr($formattedCode, 0, 8) . '...',
            'student_identifier' => $studentIdentifier,
            'status' => 'available',
            'role' => $role,
            'expires_at' => $expiresAt,
        ]);

        return [
            'raw_code' => $formattedCode,
            'access_code' => $instance,
        ];
    }

    public static function findByRawCode(string $rawCode): ?self
    {
        $normalized = trim(strtoupper($rawCode));
        $hash = hash('sha256', $normalized);
        return self::where('code_hash', $hash)->first();
    }

    public function isValidForLogin(): bool
    {
        if ($this->status === 'revoked' || $this->status === 'banned') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }
}
