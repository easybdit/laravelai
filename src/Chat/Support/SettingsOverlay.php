<?php

namespace EasyAI\LaravelAI\Chat\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies any admin-saved overrides (from the Settings UI) on top of
 * config()/.env at boot. Config/env stays the source of truth for anyone
 * who never opens the Settings page — this only ever touches keys an
 * admin has explicitly saved.
 *
 * Deliberately defensive: never throws, and no-ops entirely until the
 * ai_settings table exists (so installs that haven't migrated yet, or
 * artisan commands running before the DB is ready, are unaffected).
 *
 * Only guarantees correctness once per fresh application boot — it applies
 * saved overrides on top of whatever config() currently holds, it does not
 * snapshot-and-restore "pristine" config to correctly un-apply a *removed*
 * override without a new boot. That's exactly right under classic PHP-FPM
 * / artisan serve (every request is a fresh process, so config/ai.php
 * re-evaluates env() from scratch before apply() ever runs). Under a
 * long-running worker (Octane, a queue worker holding the app booted), a
 * setting that was cleared takes effect on that worker's next reboot, same
 * caveat every DB-backed config-override package has.
 */
class SettingsOverlay
{
    private const CACHE_KEY = 'laravelai:settings-overlay';
    private const CACHE_TTL = 30; // seconds — bounds DB load without needing manual cache invalidation everywhere

    public static function apply(): void
    {
        try {
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                if (!Schema::hasTable('ai_settings')) {
                    return [];
                }
                return DB::table('ai_settings')->pluck('value', 'key')->all();
            });

            foreach ($rows as $key => $rawValue) {
                config([$key => json_decode((string) $rawValue, true)]);
            }
        } catch (\Throwable) {
            // A settings-table/cache problem must never stop the app from booting.
        }
    }

    /** Call after saving from the Settings UI so the new value is live immediately. */
    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Non-fatal — worst case the overlay is briefly stale until the TTL expires.
        }
    }
}
