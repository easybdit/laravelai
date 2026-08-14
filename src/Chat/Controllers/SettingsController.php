<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\AiAdmin;
use EasyAI\LaravelAI\Chat\Models\AiSetting;
use EasyAI\LaravelAI\Chat\Support\SettingsOverlay;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Admin UI for changing provider settings without touching .env — fields
 * saved here go through SettingsOverlay and simply override config() from
 * then on. Fail-closed by design (see config('ai.chat.settings_gate')):
 * there is no "everyone" mode for editing API keys, independent of
 * whatever config('ai.chat.middleware')/access-restriction the main chat
 * page itself uses.
 */
class SettingsController extends Controller
{
    private const PROVIDER_FIELDS = [
        'ollama'    => ['url', 'model', 'timeout', 'keep_alive'],
        'openai'    => ['api_key', 'model', 'timeout'],
        'anthropic' => ['api_key', 'model', 'timeout'],
        'deepseek'  => ['api_key', 'model', 'timeout'],
        'gemini'    => ['api_key', 'model', 'timeout'],
        'together'  => ['api_key', 'model', 'timeout'],
    ];

    private const SECRET_FIELDS = ['api_key'];

    private const MASK = '••••••••••••';

    private function authorize(Request $request): void
    {
        $gate = config('ai.chat.settings_gate', 'manage-ai-settings');
        if (!$request->user() || !Gate::forUser($request->user())->allows($gate)) {
            abort(403, 'You do not have permission to manage AI settings.');
        }
    }

    public function edit(Request $request)
    {
        $this->authorize($request);

        $providers = [];
        foreach (self::PROVIDER_FIELDS as $name => $fields) {
            foreach ($fields as $field) {
                $value = config("ai.providers.{$name}.{$field}");
                $providers[$name][$field] = (in_array($field, self::SECRET_FIELDS, true) && $value)
                    ? self::MASK . mb_substr((string) $value, -4)
                    : $value;
            }
        }

        // Even masked, this page shouldn't sit in a shared/proxy cache or a
        // shared computer's back button history.
        return response()
            ->view('laravelai::settings', [
                'providers'       => $providers,
                'providerLabels'  => $this->providerLabels(),
                'secretFields'    => self::SECRET_FIELDS,
                'defaultProvider' => config('ai.default'),
                'status'          => $request->session()->get('status'),
                'admins'          => $this->adminsWithEmail(),
                'currentUserId'   => (int) $request->user()->getAuthIdentifier(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Grants access to /ai-chat/settings to another user by email — the
     * scalable path once at least one admin already exists (the very
     * first one has to come from `php artisan laravelai:make-admin`,
     * since this UI is itself gated by that same admin check).
     */
    public function addAdmin(Request $request)
    {
        $this->authorize($request);
        $request->validate(['email' => 'required|email']);

        $user = $this->userModel()::where('email', $request->input('email'))->first();

        if (!$user) {
            return redirect()->route('ai-chat.settings.edit')
                ->with('status', "No user found with that email — they need to register in your app first.");
        }

        AiAdmin::firstOrCreate(['user_id' => $user->getAuthIdentifier()]);

        return redirect()->route('ai-chat.settings.edit')
            ->with('status', "{$request->input('email')} can now manage AI settings.");
    }

    public function removeAdmin(Request $request, AiAdmin $admin)
    {
        $this->authorize($request);

        // Never let this page remove its own last remaining admin — that's
        // a self-inflicted lockout with no UI path back in, only another
        // trip to `php artisan laravelai:make-admin`.
        if (AiAdmin::count() <= 1) {
            return redirect()->route('ai-chat.settings.edit')
                ->with('status', "Can't remove the last admin — add another admin first.");
        }

        $admin->delete();

        return redirect()->route('ai-chat.settings.edit')->with('status', 'Admin access removed.');
    }

    public function update(Request $request)
    {
        $this->authorize($request);

        $request->validate([
            'default_provider' => 'required|string|in:' . implode(',', array_keys(self::PROVIDER_FIELDS)),
        ]);

        $this->save('ai.default', $request->input('default_provider'));

        foreach (self::PROVIDER_FIELDS as $name => $fields) {
            foreach ($fields as $field) {
                if (!$request->has("providers.{$name}.{$field}")) {
                    continue;
                }
                $input = trim((string) $request->input("providers.{$name}.{$field}"));

                // A masked secret left untouched — never overwrite the real value with the mask itself.
                if (in_array($field, self::SECRET_FIELDS, true) && str_starts_with($input, self::MASK)) {
                    continue;
                }

                $key = "ai.providers.{$name}.{$field}";
                if ($input === '') {
                    AiSetting::where('key', $key)->delete(); // blank = fall back to config()/.env again
                    continue;
                }

                $castField = in_array($field, ['timeout'], true) ? (int) $input : $input;
                $this->save($key, $castField);
            }
        }

        SettingsOverlay::forgetCache();

        return redirect()->route('ai-chat.settings.edit')->with('status', 'Settings saved.');
    }

    /**
     * Test-connection endpoint reused from the Settings page — same
     * ->health() every driver already exposes, just surfaced in the UI.
     */
    public function test(Request $request)
    {
        $this->authorize($request);
        $request->validate(['provider' => 'required|string']);

        try {
            $ok = AI::provider($request->input('provider'))->health();
            return response()->json(['ok' => $ok]);
        } catch (\Throwable) {
            // Every driver's health() already fails safe internally and
            // never leaks its exception message (verified — none of them
            // put a credential anywhere an exception message could echo it
            // back). This outer catch only ever fires on provider
            // *resolution* errors (e.g. an unknown provider name), but a
            // generic message here costs nothing and keeps that guarantee
            // airtight even if a future driver's exception text changes.
            return response()->json(['ok' => false, 'error' => 'Could not reach this provider.']);
        }
    }

    private function save(string $key, mixed $value): void
    {
        if (SettingsOverlay::isSecretKey($key) && is_string($value) && $value !== '') {
            $value = SettingsOverlay::encryptForStorage($value);
        }

        AiSetting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }

    private function providerLabels(): array
    {
        return [
            'ollama'    => 'Ollama (Local)',
            'openai'    => 'OpenAI (ChatGPT)',
            'anthropic' => 'Anthropic (Claude)',
            'deepseek'  => 'DeepSeek',
            'gemini'    => 'Google Gemini',
            'together'  => 'Together AI',
        ];
    }

    /**
     * The host app's own User model class — resolved dynamically via
     * Laravel's own standard auth config rather than assuming \App\Models\User,
     * since this package has no way to know that for certain.
     */
    private function userModel(): string
    {
        return config('auth.providers.users.model', \App\Models\User::class);
    }

    /**
     * @return array<int, array{id: int, user_id: int, email: string}>
     */
    private function adminsWithEmail(): array
    {
        $model = $this->userModel();

        return AiAdmin::orderBy('id')->get()->map(function (AiAdmin $admin) use ($model) {
            $user = class_exists($model) ? $model::find($admin->user_id) : null;

            return [
                'id'      => $admin->id,
                'user_id' => (int) $admin->user_id,
                'email'   => $user->email ?? "user #{$admin->user_id} (account not found)",
            ];
        })->all();
    }
}
