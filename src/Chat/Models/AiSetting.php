<?php

namespace EasyAI\LaravelAI\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per overridden config() key. Values are stored JSON-encoded so
 * booleans/ints/strings round-trip correctly (see SettingsOverlay);
 * secret keys (api_key, etc.) are additionally encrypted before storage —
 * see SettingsOverlay::isSecretKey()/encryptForStorage().
 */
class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = ['key', 'value'];

    // Defense-in-depth: nothing in this package ever serializes this model
    // directly today (config() is always read through, not this model),
    // but hiding `value` means an accidental future toArray()/toJson() on
    // it can't leak a credential either.
    protected $hidden = ['value'];
}
