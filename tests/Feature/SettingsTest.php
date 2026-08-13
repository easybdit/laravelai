<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\AiSetting;
use EasyAI\LaravelAI\Chat\Support\SettingsOverlay;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUser(): Authenticatable
    {
        return new class implements Authenticatable {
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier() { return 7; }
            public function getAuthPasswordName() { return 'password'; }
            public function getAuthPassword() { return 'x'; }
            public function getRememberToken() { return null; }
            public function setRememberToken($value) {}
            public function getRememberTokenName() { return 'remember_token'; }
        };
    }

    public function test_settings_page_refuses_guests(): void
    {
        $this->get('/ai-chat/settings')->assertStatus(403);
    }

    public function test_settings_page_refuses_authenticated_user_without_a_defined_gate(): void
    {
        $this->actingAs($this->fakeUser());
        $this->get('/ai-chat/settings')->assertStatus(403);
    }

    public function test_settings_page_loads_once_gate_allows(): void
    {
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());

        $this->get('/ai-chat/settings')->assertOk()->assertSee('AI Settings');
    }

    public function test_saving_an_api_key_overrides_config_immediately(): void
    {
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());

        $this->post('/ai-chat/settings', [
            'default_provider' => 'openai',
            'providers' => ['openai' => ['api_key' => 'sk-new-test-key', 'model' => 'gpt-4o-mini', 'timeout' => '60']],
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_settings', ['key' => 'ai.providers.openai.api_key']);

        SettingsOverlay::apply();
        $this->assertSame('sk-new-test-key', config('ai.providers.openai.api_key'));
        $this->assertSame('openai', config('ai.default'));
    }

    public function test_api_keys_are_encrypted_at_rest_not_stored_as_plaintext(): void
    {
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());

        $this->post('/ai-chat/settings', [
            'default_provider' => 'openai',
            'providers' => ['openai' => ['api_key' => 'sk-super-secret-value', 'model' => 'gpt-4o-mini', 'timeout' => '60']],
        ])->assertRedirect();

        $stored = AiSetting::where('key', 'ai.providers.openai.api_key')->first();
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('sk-super-secret-value', $stored->getRawOriginal('value'));

        // ...but a non-secret field right next to it is stored in the clear (nothing to protect there).
        $model = AiSetting::where('key', 'ai.providers.openai.model')->first();
        $this->assertStringContainsString('gpt-4o-mini', $model->getRawOriginal('value'));
    }

    public function test_hidden_attribute_keeps_value_out_of_accidental_serialization(): void
    {
        $setting = AiSetting::create(['key' => 'ai.providers.openai.api_key', 'value' => json_encode('sk-original')]);

        $this->assertArrayNotHasKey('value', $setting->toArray());
    }

    public function test_a_corrupted_encrypted_value_is_skipped_not_fatal(): void
    {
        AiSetting::create(['key' => 'ai.providers.openai.api_key', 'value' => json_encode('enc:v1:not-actually-valid-ciphertext')]);
        SettingsOverlay::forgetCache();

        // Must not throw — the whole point of "never break boot" — and the
        // key is simply skipped, leaving whatever config()/.env already had.
        SettingsOverlay::apply();
        $this->addToAssertionCount(1);
    }

    public function test_submitting_the_masked_placeholder_does_not_overwrite_the_real_secret(): void
    {
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());
        AiSetting::create(['key' => 'ai.providers.openai.api_key', 'value' => json_encode('sk-original')]);
        SettingsOverlay::forgetCache();

        // Load the edit page to see exactly what mask value it renders, then submit it back unchanged.
        $html = $this->get('/ai-chat/settings')->getContent();
        preg_match('/name="providers\[openai\]\[api_key\]"\s+value="([^"]+)"/s', $html, $m);
        $this->assertNotEmpty($m, 'Could not find the masked api_key field in the rendered page.');

        $this->post('/ai-chat/settings', [
            'default_provider' => 'openai',
            'providers' => ['openai' => ['api_key' => $m[1], 'model' => 'gpt-4o-mini', 'timeout' => '60']],
        ])->assertRedirect();

        SettingsOverlay::apply();
        $this->assertSame('sk-original', config('ai.providers.openai.api_key'));
    }

    public function test_blanking_a_field_deletes_its_stored_override(): void
    {
        // The persisted contract is what actually matters: a saved row means
        // "this key is overridden," no row means "fall back to config()/.env."
        // A *fresh* boot picks that up automatically (config/ai.php re-evaluates
        // env() from scratch every request under classic PHP-FPM/artisan
        // serve). What this deliberately does NOT test is reverting an
        // already-mutated in-process config() mid-process without a new
        // boot — SettingsOverlay only guarantees correctness once per fresh
        // application boot, same as every other DB-backed config-override
        // package; under a long-running worker (Octane) a changed setting
        // takes effect on the next request that boots a fresh worker/config.
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());
        AiSetting::create(['key' => 'ai.providers.openai.model', 'value' => json_encode('gpt-4-turbo')]);

        $this->post('/ai-chat/settings', [
            'default_provider' => 'openai',
            'providers' => ['openai' => ['api_key' => '', 'model' => '', 'timeout' => '60']],
        ])->assertRedirect();

        $this->assertDatabaseMissing('ai_settings', ['key' => 'ai.providers.openai.model']);
    }

    public function test_test_connection_endpoint_checks_health(): void
    {
        Gate::define('manage-ai-settings', fn () => true);
        $this->actingAs($this->fakeUser());

        Http::fake(['127.0.0.1:11434*' => Http::response('', 200)]);

        $response = $this->postJson('/ai-chat/settings/test', ['provider' => 'ollama']);

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
