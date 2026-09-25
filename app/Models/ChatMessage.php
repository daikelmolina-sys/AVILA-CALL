<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_class_id',
        'access_code_id',
        'user_id',
        'sender_name',
        'sender_role',
        'message',
        'is_deleted',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
        ];
    }

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
