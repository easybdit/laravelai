<?php

namespace EasyAI\LaravelAI\Console;

use EasyAI\LaravelAI\Chat\Models\AiAdmin;
use Illuminate\Console\Command;

/**
 * `php artisan laravelai:make-admin {email}` — grants access to
 * /ai-chat/settings. Solves a real bootstrap problem: the Settings page's
 * own "Manage Admins" panel is itself gated by at least one admin already
 * existing, so the very first admin has to come from somewhere with direct
 * server/CLI access rather than the UI — same shape as Laravel ecosystem
 * tools that need an initial privileged account (e.g. `make:filament-user`).
 * Every admin after the first can be added from the Settings page itself.
 *
 * Idempotent and safe to re-run: an email that's already an admin is a
 * no-op, not an error.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'laravelai:make-admin {email? : Email of the user to grant AI Settings access to}';

    protected $description = 'Grant a user access to the AI Settings page (/ai-chat/settings)';

    public function handle(): int
    {
        $model = config('auth.providers.users.model', \App\Models\User::class);

        if (!class_exists($model)) {
            $this->error("Could not find the configured user model [{$model}]. If your app's User model isn't the default, set auth.providers.users.model in config/auth.php.");
            return 1;
        }

        $email = $this->argument('email') ?: $this->ask('Email of the user to grant AI Settings access to');

        if (!$email) {
            $this->error('An email is required.');
            return 1;
        }

        $user = $model::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email [{$email}]. They need to register in your app first.");
            return 1;
        }

        $admin = AiAdmin::firstOrCreate(['user_id' => $user->getAuthIdentifier()]);

        $this->info(
            $admin->wasRecentlyCreated
                ? "✅ {$email} can now manage AI settings — visit /ai-chat/settings."
                : "{$email} already has access to AI settings — nothing to do."
        );

        return 0;
    }
}
