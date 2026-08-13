<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per overridden config() key. Values are stored JSON-encoded so
 * booleans/ints/strings round-trip correctly (see SettingsOverlay).
 */
class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = ['key', 'value'];
}
