<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    // See ChatSession's own $table — same rename, same reasoning
    // (2026_08_14_000005_rename_chat_tables_for_namespace_safety).
    protected $table = 'ai_chat_messages';

    protected $fillable = ['chat_session_id', 'role', 'content', 'rating'];

    public function session(): BelongsTo
    {
        // Explicit FK — the relationship method is named "session" but the
        // column is "chat_session_id"; Eloquent's default convention would
        // otherwise look for "session_id" and silently return null.
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }
}
