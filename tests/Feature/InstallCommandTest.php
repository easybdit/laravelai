<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\AiAdmin;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Self-contained fixture, deliberately not shared with AdminAccessTest's
 * own TestAdminUser — cross-file class reuse for a fixture this small
 * would only make both files more fragile to run in isolation.
 */
class InstallCommandTestUser extends Authenticatable
{
    protected $table = 'users';
    protected $fillable = ['name', 'email'];
    public $timestamps = false;
}

/**
 * `php artisan laravelai:install` — the guided one-command setup. Every
 * test here runs against a disposable temp directory as the app's
 * base_path() (see defineEnvironment()) so the command's .env read/write
 * never touches this repo's real .env or the shared Testbench sandbox.
 */
class InstallCommandTest extends TestCase
{
    private string $tempBasePath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->tempBasePath = sys_get_temp_dir() . '/laravelai_install_test_' . uniqid();
        mkdir($this->tempBasePath, 0777, true);

        $app->setBasePath($this->tempBasePath);

        // Sidestep needing a database/ directory under the temp base path
        // entirely — an in-memory connection needs no file at all.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function tearDown(): void
    {
        if (!empty($this->tempBasePath) && is_dir($this->tempBasePath)) {
            $this->deleteDirectory($this->tempBasePath);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function envPath(): string
    {
        return $this->tempBasePath . DIRECTORY_SEPARATOR . '.env';
    }

    public function test_full_interactive_run_with_ollama_writes_env_and_succeeds(): void
    {
        Http::fake(['*' => Http::response('Ollama is running', 200)]);

        $this->artisan('laravelai:install')
            ->expectsConfirmation('Run database migrations now?', 'yes')
            ->expectsQuestion(
                'Which email should be able to manage AI settings later (/ai-chat/settings)? Leave blank to skip for now',
                ''
            )
            ->expectsChoice(
                'Which AI provider do you want to use?',
                'Ollama (free, self-hosted)',
                [
                    'Ollama (free, self-hosted)',
                    'OpenAI (ChatGPT)',
                    'Anthropic (Claude)',
                    'DeepSeek',
                    'Google Gemini',
                ]
            )
            ->expectsQuestion('Ollama server URL', 'http://127.0.0.1:11434')
            ->expectsQuestion(
                'Ollama model (default qwen2:1.5b — fast/light; try llama3.1:8b for a larger, more capable model)',
                'qwen2:1.5b'
            )
            ->expectsOutputToContain('Setting up LaravelAI')
            ->expectsOutputToContain('/ai-chat')
            ->assertExitCode(0);

        $this->assertFileExists($this->envPath());

        $contents = file_get_contents($this->envPath());
        $this->assertStringContainsString('AI_PROVIDER=ollama', $contents);
        $this->assertStringContainsString('AI_OLLAMA_URL=http://127.0.0.1:11434', $contents);
        $this->assertStringContainsString('AI_OLLAMA_MODEL=qwen2:1.5b', $contents);
    }

    public function test_does_not_overwrite_an_existing_non_empty_provider_value(): void
    {
        file_put_contents($this->envPath(), "APP_NAME=Test\nAI_PROVIDER=openai\n");

        $this->artisan('laravelai:install')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->expectsChoice(
                'Which AI provider do you want to use?',
                'Anthropic (Claude)',
                [
                    'Ollama (free, self-hosted)',
                    'OpenAI (ChatGPT)',
                    'Anthropic (Claude)',
                    'DeepSeek',
                    'Google Gemini',
                ]
            )
            ->expectsQuestion('Your Anthropic API key', 'sk-test-anthropic-key')
            ->expectsQuestion('Anthropic model', 'claude-sonnet-4-20250514')
            ->expectsOutputToContain('AI_PROVIDER already has a value in .env')
            ->assertExitCode(0);

        $contents = file_get_contents($this->envPath());

        // The pre-existing, real AI_PROVIDER value is untouched — never
        // overwritten with the newly-chosen "anthropic".
        $this->assertSame(1, substr_count($contents, 'AI_PROVIDER='));
        $this->assertStringContainsString('AI_PROVIDER=openai', $contents);
        $this->assertStringNotContainsString('AI_PROVIDER=anthropic', $contents);

        // But genuinely new keys for the chosen provider are still appended.
        $this->assertStringContainsString('AI_ANTHROPIC_KEY=sk-test-anthropic-key', $contents);
        $this->assertStringContainsString('AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514', $contents);
    }

    public function test_force_option_skips_already_exists_confirmations(): void
    {
        Http::fake(['*' => Http::response('Ollama is running', 200)]);

        // Pre-create both published targets so, without --force, the
        // command would normally stop and ask before overwriting either.
        mkdir(dirname(config_path('ai.php')), 0777, true);
        file_put_contents(config_path('ai.php'), "<?php\nreturn [];\n");

        mkdir(public_path('vendor/laravelai'), 0777, true);
        file_put_contents(public_path('vendor/laravelai/existing.txt'), 'stale asset');

        // No "already exists — overwrite?" confirmations are registered
        // below — if --force failed to skip them, Mockery would fail this
        // test on the first unexpected question.
        $this->artisan('laravelai:install', ['--force' => true])
            ->expectsConfirmation('Run database migrations now?', 'yes')
            ->expectsQuestion(
                'Which email should be able to manage AI settings later (/ai-chat/settings)? Leave blank to skip for now',
                ''
            )
            ->expectsChoice(
                'Which AI provider do you want to use?',
                'Ollama (free, self-hosted)',
                [
                    'Ollama (free, self-hosted)',
                    'OpenAI (ChatGPT)',
                    'Anthropic (Claude)',
                    'DeepSeek',
                    'Google Gemini',
                ]
            )
            ->expectsQuestion('Ollama server URL', 'http://127.0.0.1:11434')
            ->expectsQuestion(
                'Ollama model (default qwen2:1.5b — fast/light; try llama3.1:8b for a larger, more capable model)',
                'qwen2:1.5b'
            )
            ->assertExitCode(0);

        $this->assertStringContainsString('AI_PROVIDER=ollama', file_get_contents($this->envPath()));
    }

    public function test_entering_an_email_grants_ai_settings_access_via_the_installer(): void
    {
        config(['auth.providers.users.model' => InstallCommandTestUser::class]);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
        });
        $user = InstallCommandTestUser::create(['name' => 'Owner', 'email' => 'owner@example.com']);

        Http::fake(['*' => Http::response('Ollama is running', 200)]);

        $this->artisan('laravelai:install')
            ->expectsConfirmation('Run database migrations now?', 'yes')
            ->expectsQuestion(
                'Which email should be able to manage AI settings later (/ai-chat/settings)? Leave blank to skip for now',
                'owner@example.com'
            )
            ->expectsChoice(
                'Which AI provider do you want to use?',
                'Ollama (free, self-hosted)',
                [
                    'Ollama (free, self-hosted)',
                    'OpenAI (ChatGPT)',
                    'Anthropic (Claude)',
                    'DeepSeek',
                    'Google Gemini',
                ]
            )
            ->expectsQuestion('Ollama server URL', 'http://127.0.0.1:11434')
            ->expectsQuestion(
                'Ollama model (default qwen2:1.5b — fast/light; try llama3.1:8b for a larger, more capable model)',
                'qwen2:1.5b'
            )
            ->expectsOutputToContain("You're already set up")
            ->assertExitCode(0);

        $this->assertDatabaseHas('ai_admins', ['user_id' => $user->id]);
    }
}
