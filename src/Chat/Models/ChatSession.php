<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSession extends Model
{
    protected $fillable = ['title', 'project_id', 'user_id', 'guest_token', 'provider', 'profile'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }

    public function toAIMessages(): array
    {
        return $this->messages->map(fn($m) => [
            'role'    => $m->role,
            'content' => $m->content,
        ])->toArray();
    }

    /**
     * Whether the given identity (auth user id + guest token) owns this session.
     * A session with no identity recorded at all (created before the identity
     * migration, or while chat.allow_guest is off and no auth is configured)
     * is treated as accessible — it predates per-identity scoping.
     */
    public function isOwnedBy(?int $userId, ?string $guestToken): bool
    {
        if (is_null($this->user_id) && is_null($this->guest_token)) {
            return true;
        }

        if ($userId) {
            return (int) $this->user_id === $userId;
        }

        return $guestToken !== null && $guestToken !== '' && $this->guest_token === $guestToken;
    }
}
