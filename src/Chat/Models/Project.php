<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    // Explicit — Eloquent's default convention would otherwise use the
    // bare "projects" table, which collides with almost any host app's
    // own "projects" concept. Renamed via a dedicated migration (not
    // edited in place) since this table already shipped in v1.4.0 —
    // existing installs need their data renamed, not just newly created
    // under a different name.
    protected $table = 'ai_projects';

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
