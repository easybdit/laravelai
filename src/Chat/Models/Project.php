<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    // Explicit — Eloquent's default convention would otherwise use the
    // bare "projects" table, which collides with almost any host app.
    protected $table = 'ai_projects';

    protected $fillable = ['name', 'description'];

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }
}
