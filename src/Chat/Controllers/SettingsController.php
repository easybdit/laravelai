<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

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

        return view('laravelai::settings', [
            'providers'       => $providers,
            'providerLabels'  => $this->providerLabels(),
            'secretFields'    => self::SECRET_FIELDS,
            'defaultProvider' => config('ai.default'),
            'status'          => $request->session()->get('status'),
        ]);
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
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function save(string $key, mixed $value): void
    {
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
}
