<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_class_id',
        'actor_type', // user, access_code
        'actor_id',
        'actor_name',
        'action', // toggle_chat, delete_message, mute_student, kick_student, ban_code, promote_moderator, demote_moderator
        'target_type',
        'target_id',
        'target_name',
        'details',
    ];

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }
}
