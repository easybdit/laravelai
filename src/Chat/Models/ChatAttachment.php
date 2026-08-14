<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAttachment extends Model
{
    // See ChatSession's own $table — same rename, same reasoning
    // (2026_08_14_000005_rename_chat_tables_for_namespace_safety).
    protected $table = 'ai_chat_attachments';

    protected $fillable = [
        'chat_session_id', 'chat_message_id', 'type', 'original_name',
        'stored_path', 'mime_type', 'size', 'extracted_text', 'status',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
