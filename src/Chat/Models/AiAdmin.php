<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per user allowed to reach /ai-chat/settings — see
 * 2026_08_14_000004_create_ai_admins_table's own docblock for why this
 * exists instead of asking every host app to hand-write a Gate. Deliberately
 * just a user_id; no relationship to the host app's User model is declared
 * here since this package doesn't know its class — SettingsController
 * resolves it dynamically via config('auth.providers.users.model') when it
 * needs a display name.
 */
class AiAdmin extends Model
{
    protected $table = 'ai_admins';

    protected $fillable = ['user_id'];
}
