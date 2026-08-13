<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'user_id', 'guest_token'];

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * Whether the given identity (auth user id + guest token) owns this
     * project. A project with no identity recorded at all (created before
     * the identity migration) is treated as accessible — see the migration
     * docblock for why.
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
