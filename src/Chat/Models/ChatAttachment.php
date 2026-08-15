<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatAttachment extends Model
{
    // See ChatSession's own $table — same rename, same reasoning
    // (2026_08_14_000005_rename_chat_tables_for_namespace_safety).
    protected $table = 'ai_chat_attachments';

    protected $fillable = [
        'chat_session_id', 'chat_message_id', 'parent_attachment_id', 'type', 'original_name',
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

    /** The PDF this page image was rendered from, when this row is one (see PdfPageRenderer). Null for every other attachment. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_attachment_id');
    }

    /** Page images rendered from this attachment, when it's a PDF and config('ai.chat.attachments.pdf_vision_enabled') was on at upload time. Empty for everything else. */
    public function pageImages(): HasMany
    {
        return $this->hasMany(self::class, 'parent_attachment_id');
    }
}
