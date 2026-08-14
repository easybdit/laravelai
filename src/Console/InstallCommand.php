<?php

namespace EasyAI\LaravelAI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * `php artisan laravelai:install` — the one-command answer to the five
 * manual steps in the README's Installation section (publish config,
 * publish assets, migrate, hand-edit .env). Guided, idempotent, and safe
 * to re-run: it never clobbers an existing config file, asset directory,
 * or a real .env value without asking first.
 */
class InstallCommand extends Command
{
    protected $signature = 'laravelai:install {--force : Overwrite existing published files without confirmation}';

    protected $description = 'Interactively set up LaravelAI — publishes config/assets, runs migrations, and configures your first AI provider';

    private bool $adminConfigured = false;

    public function handle(): int
    {
        $this->info('🤖 Setting up LaravelAI...');
        $this->line('A guided, zero-hassle setup — publishes config/assets, runs migrations, and configures your first AI provider.');
        $this->newLine();

        $this->publishConfig();
        $this->publishChatAssets();
        $migrated = $this->maybeMigrate();

        if ($migrated) {
            $this->maybeCreateAdmin();
        }

        [$providerKey, $envPairs] = $this->configureProvider();

        if (!$this->writeEnvironment($envPairs)) {
            return 1;
        }

        $this->healthCheck($providerKey, $envPairs);
        $this->summary();

        return 0;
    }

    private function publishConfig(): void
    {
        $target = config_path('ai.php');
        $force  = (bool) $this->option('force');

        if (file_exists($target) && !$force) {
            if (!$this->confirm('config/ai.php already exists — overwrite?', false)) {
                $this->line('Skipping config publish (keeping your existing config/ai.php).');
                return;
            }
            $force = true;
        }

        $this->call('vendor:publish', ['--tag' => 'ai-config', '--force' => $force]);
    }

    private function publishChatAssets(): void
    {
        $target = public_path('vendor/laravelai');
        $force  = (bool) $this->option('force');

        if (is_dir($target) && !$force) {
            if (!$this->confirm('public/vendor/laravelai already exists — overwrite?', false)) {
                $this->line('Skipping asset publish (keeping your existing files).');
                return;
            }
            $force = true;
        }

        $this->call('vendor:publish', ['--tag' => 'ai-chat-assets', '--force' => $force]);
    }

    private function maybeMigrate(): bool
    {
        $this->newLine();

        // Every migration in this package guards itself with
        // Schema::hasTable()/hasColumn(), so re-running is additive/safe —
        // but some teams manage migrations separately (CI, deploy step)
        // and wouldn't want an installer running one ad hoc, so ask first.
        if ($this->confirm('Run database migrations now?', true)) {
            $this->call('migrate');
            return true;
        }

        $this->line('Skipping migrations — run `php artisan migrate` yourself before using the chat UI.');
        return false;
    }

    /**
     * The Settings page (/ai-chat/settings) is fail-closed by default —
     * nobody can reach it until at least one row exists in ai_admins, and
     * that first row is a genuine bootstrap problem (the UI that manages
     * admins is itself gated by one already existing). Folding it into the
     * installer means most people never have to know that detail exists —
     * only reached when maybeMigrate() actually ran migrations, since the
     * ai_admins table has to exist first. Entirely skippable: leaving the
     * email blank does nothing, exactly like never running
     * `laravelai:make-admin` at all — the old manual path still works.
     */
    private function maybeCreateAdmin(): void
    {
        $this->newLine();
        $email = $this->ask('Which email should be able to manage AI settings later (/ai-chat/settings)? Leave blank to skip for now');

        if (!$email) {
            $this->line('Skipping — run `php artisan laravelai:make-admin` yourself whenever you\'re ready to grant this.');
            return;
        }

        $exitCode = $this->call('laravelai:make-admin', ['email' => $email]);
        $this->adminConfigured = $exitCode === 0;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function configureProvider(): array
    {
        $providers = [
            'ollama'    => 'Ollama (free, self-hosted)',
            'openai'    => 'OpenAI (ChatGPT)',
            'anthropic' => 'Anthropic (Claude)',
            'deepseek'  => 'DeepSeek',
            'gemini'    => 'Google Gemini',
        ];

        $this->newLine();
        $chosenLabel = $this->choice('Which AI provider do you want to use?', array_values($providers), 0);
        $providerKey = (string) array_search($chosenLabel, $providers, true);

        $envPairs = ['AI_PROVIDER' => $providerKey];

        switch ($providerKey) {
            case 'ollama':
                $envPairs['AI_OLLAMA_URL'] = $this->ask('Ollama server URL', 'http://127.0.0.1:11434');
                $envPairs['AI_OLLAMA_MODEL'] = $this->ask(
                    'Ollama model (default qwen2:1.5b — fast/light; try llama3.1:8b for a larger, more capable model)',
                    'qwen2:1.5b'
                );
                break;

            case 'openai':
                $envPairs['AI_OPENAI_KEY']   = (string) $this->secret('Your OpenAI API key');
                $envPairs['AI_OPENAI_MODEL'] = $this->ask('OpenAI model', 'gpt-4o-mini');
                break;

            case 'anthropic':
                $envPairs['AI_ANTHROPIC_KEY']   = (string) $this->secret('Your Anthropic API key');
                $envPairs['AI_ANTHROPIC_MODEL'] = $this->ask('Anthropic model', 'claude-sonnet-4-20250514');
                break;

            case 'deepseek':
                $envPairs['AI_DEEPSEEK_KEY']   = (string) $this->secret('Your DeepSeek API key');
                $envPairs['AI_DEEPSEEK_MODEL'] = $this->ask('DeepSeek model', 'deepseek-chat');
                break;

            case 'gemini':
                $envPairs['AI_GEMINI_KEY']   = (string) $this->secret('Your Google Gemini API key');
                $envPairs['AI_GEMINI_MODEL'] = $this->ask('Gemini model', 'gemini-2.0-flash');
                break;
        }

        return [$providerKey, $envPairs];
    }

    /**
     * @param array<string, string> $envPairs
     */
    private function writeEnvironment(array $envPairs): bool
    {
        $this->newLine();
        $this->info('Writing configuration to .env...');

        try {
            foreach ($envPairs as $key => $value) {
                $this->setEnvKey($key, $value);
            }
        } catch (\Throwable $e) {
            $this->error('Could not write to .env: ' . $e->getMessage());
            $this->line('Add these lines to your .env manually:');
            foreach ($envPairs as $key => $value) {
                $this->line("  {$key}={$value}");
            }

            return false;
        }

        return true;
    }

    /**
     * Append a key to the host app's .env — never overwrite a key that
     * already has a real value, only fill in truly-empty ones (e.g.
     * `AI_PROVIDER=` with nothing after the `=`) or append genuinely new
     * ones. Never corrupt an existing working .env.
     */
    private function setEnvKey(string $key, string $value): void
    {
        $envPath = base_path('.env');

        $contents = file_exists($envPath) ? (file_get_contents($envPath) ?: '') : '';
        if ($contents === '' && !file_exists($envPath) && !is_dir(dirname($envPath))) {
            throw new \RuntimeException("Directory for .env does not exist: " . dirname($envPath));
        }

        $formattedValue = $this->formatEnvValue($value);
        $pattern        = '/^' . preg_quote($key, '/') . '=(.*)$/m';

        if (preg_match($pattern, $contents, $matches)) {
            $existing = trim($matches[1]);

            if ($existing !== '') {
                $this->warn("{$key} already has a value in .env — leaving it untouched.");
                return;
            }

            $contents = preg_replace($pattern, $key . '=' . $formattedValue, $contents, 1);
        } else {
            if ($contents !== '' && !str_ends_with($contents, "\n")) {
                $contents .= "\n";
            }
            $contents .= $key . '=' . $formattedValue . "\n";
        }

        if (file_put_contents($envPath, $contents) === false) {
            throw new \RuntimeException("Failed to write {$key} to .env");
        }
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    /**
     * @param array<string, string> $envPairs
     */
    private function healthCheck(string $providerKey, array $envPairs): void
    {
        $this->newLine();
        $this->info('Checking connection...');

        if ($providerKey !== 'ollama') {
            $this->line("Skipping live check for {$providerKey} — your API key will be validated on first real use.");
            return;
        }

        $url = $envPairs['AI_OLLAMA_URL'] ?? config('ai.providers.ollama.url', 'http://127.0.0.1:11434');

        try {
            // Same approach as OllamaDriver::health() — a short-timeout GET
            // against the server root, no auth required.
            $response = Http::timeout(5)->get(rtrim($url, '/'));

            if ($response->successful()) {
                $this->info("✓ Connected to Ollama at {$url}");
            } else {
                $this->warn("Could not reach Ollama at {$url} (HTTP {$response->status()}). Make sure Ollama is running.");
            }
        } catch (\Throwable $e) {
            $this->warn("Could not reach Ollama at {$url}: {$e->getMessage()}. Make sure Ollama is running (see https://ollama.com).");
        }
    }

    private function summary(): void
    {
        $this->newLine();
        $this->info('✅ LaravelAI is set up! Visit /ai-chat in your browser to start chatting.');

        if ($this->adminConfigured) {
            $this->line('Want the Settings page too? You\'re already set up — visit /ai-chat/settings and log in as the email you just entered.');
        } else {
            $this->line('Want the Settings page too? It\'s fail-closed by default — grant yourself access with:');
            $this->line('  php artisan laravelai:make-admin your@email.com');
            $this->line('Then log in as that user and visit /ai-chat/settings — more admins can be added from that page afterward, no code needed.');
        }
    }
}
