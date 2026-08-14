<?php

namespace EasyAI\LaravelAI\Tests;

use EasyAI\LaravelAI\AIServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AIServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['AI' => \EasyAI\LaravelAI\Facades\AI::class];
    }

    protected function defineEnvironment($app): void
    {
        // The chat routes run inside the 'web' middleware group (sessions,
        // cookies, CSRF) — EncryptCookies needs a real app key to boot.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $this->configureDatabase($app);

        $app['config']->set('ai.default', 'ollama');
        $app['config']->set('ai.providers.ollama', [
            'driver'  => 'ollama',
            'url'     => 'http://127.0.0.1:11434',
            'model'   => 'llama3.1:8b',
            'timeout' => 30,
            'options' => ['temperature' => 0.7],
        ]);
        $app['config']->set('ai.providers.openai', [
            'driver'  => 'openai',
            'api_key' => 'test-key',
            'url'     => 'https://api.openai.com/v1',
            'model'   => 'gpt-4o-mini',
            'timeout' => 30,
            'options' => ['temperature' => 0.7, 'max_tokens' => 100],
        ]);
        $app['config']->set('ai.providers.anthropic', [
            'driver'  => 'anthropic',
            'api_key' => 'test-key',
            'url'     => 'https://api.anthropic.com/v1',
            'model'   => 'claude-sonnet-4-20250514',
            'version' => '2023-06-01',
            'timeout' => 30,
            'options' => ['max_tokens' => 100],
        ]);
        $app['config']->set('ai.providers.deepseek', [
            'driver'  => 'deepseek',
            'api_key' => 'test-key',
            'url'     => 'https://api.deepseek.com/v1',
            'model'   => 'deepseek-chat',
            'timeout' => 30,
            'options' => ['temperature' => 0.7, 'max_tokens' => 100],
        ]);
    }

    /**
     * No-DB_CONNECTION-set environments (plain `vendor/bin/phpunit`, and
     * CI's PHP-version matrix legs) get an explicit in-memory sqlite
     * connection here rather than trusting Testbench's own built-in
     * default to already be sqlite — confirmed, while chasing a real CI
     * failure, that this assumption is version-dependent and false for
     * orchestra/testbench 8.x specifically: its own skeleton
     * config/database.php defaults to `env('DB_CONNECTION', 'mysql')`,
     * not sqlite (a real convention difference from newer testbench/
     * Laravel versions' sqlite-by-default skeleton). Every test using this
     * TestCase previously worked purely because every testbench version
     * tested so far happened to default to sqlite already — the moment a
     * testbench 8.x/Laravel 10.x CI leg existed, every test touching the
     * database failed with a real "connection refused" against a mysql
     * server that was never started, since nothing here was actually
     * forcing sqlite. CI's mysql/pgsql matrix legs
     * (.github/workflows/tests.yml, v2.7.0) still set DB_CONNECTION/
     * DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD to point the
     * whole suite at a real service-container database instead, so
     * driver-specific migration/query code (e.g. the MySQL-specific
     * ALTER…MODIFY in 2026_08_14_000003_add_queued_status_to_project_files.php)
     * actually gets exercised somewhere in CI, not just SQLite.
     */
    private function configureDatabase($app): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver'                  => 'sqlite',
                'database'                => ':memory:',
                'prefix'                  => '',
                'foreign_key_constraints' => true,
            ]);
            return;
        }

        $config = [
            'driver'   => $driver,
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', $driver === 'pgsql' ? 5432 : 3306),
            'database' => env('DB_DATABASE', 'laravelai_test'),
            'username' => env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix'   => '',
        ];

        // mysql's grammar reads collation off the connection config — an
        // absent key isn't fatal (Laravel/MySQL both fall back to a
        // server default), but leaving it unset is exactly the kind of gap
        // that behaves fine on one server's default settings and not
        // another's, which is the whole reason this matrix exists.
        if ($driver === 'mysql') {
            $config['collation'] = 'utf8mb4_unicode_ci';
        }

        $app['config']->set('database.default', $driver);
        $app['config']->set("database.connections.{$driver}", $config);
    }
}
