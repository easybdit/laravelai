<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
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
