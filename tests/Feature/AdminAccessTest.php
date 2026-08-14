<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\AiAdmin;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal stand-in for the host app's own User model — nothing in this
 * repo's test suite has needed a real authenticated user before now (every
 * other /ai-chat test uses the guest-cookie identity path), so there's no
 * existing fixture to reuse. Points at a plain "users" table this test
 * creates itself; config('auth.providers.users.model') is pointed at this
 * class so SettingsController/MakeAdminCommand resolve it exactly the way
 * they'd resolve a real host app's User model.
 */
class TestAdminUser extends Authenticatable
{
    protected $table = 'users';
    protected $fillable = ['name', 'email'];
    public $timestamps = false;
}

/**
 * config('ai.chat.settings_gate') / the ai_admins table — the scalable
 * replacement for hand-writing Gate::define('manage-ai-settings', ...)
 * in the host app. Covers: the package's own zero-config default gate,
 * that a host app's own Gate::define() still wins over it (the whole
 * design depends on this), the CLI bootstrap command, and the
 * add/remove-admin UI actions including the "never lock everyone out"
 * guard.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', TestAdminUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
            });
        }
    }

    private function makeUser(string $email, string $name = 'Test User'): TestAdminUser
    {
        return TestAdminUser::create(['name' => $name, 'email' => $email]);
    }

    public function test_gate_denies_a_user_with_no_admin_row(): void
    {
        $user = $this->makeUser('nobody@example.com');

        $this->assertFalse(Gate::forUser($user)->allows('manage-ai-settings'));
    }

    public function test_gate_allows_a_user_with_an_admin_row(): void
    {
        $user = $this->makeUser('admin@example.com');
        AiAdmin::create(['user_id' => $user->id]);

        $this->assertTrue(Gate::forUser($user)->allows('manage-ai-settings'));
    }

    /**
     * The whole design hinges on this: a host app that defines its own
     * Gate for the same ability must win, so this package's default never
     * fights a real role/permission system the host app already has.
     */
    public function test_a_host_app_defined_gate_overrides_the_package_default(): void
    {
        $user = $this->makeUser('nobody-special@example.com');
        // Deliberately NOT in ai_admins — the package's own default would
        // deny this user. A host app's own Gate::define() should still win.
        Gate::define('manage-ai-settings', fn ($u) => $u->email === 'nobody-special@example.com');

        $this->assertTrue(Gate::forUser($user)->allows('manage-ai-settings'));
    }

    public function test_settings_page_403s_without_admin_access(): void
    {
        $user = $this->makeUser('guest-admin@example.com');

        $this->actingAs($user)->get('/ai-chat/settings')->assertStatus(403);
    }

    public function test_settings_page_loads_with_admin_access(): void
    {
        $user = $this->makeUser('real-admin@example.com');
        AiAdmin::create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/ai-chat/settings')->assertStatus(200);
    }

    public function test_make_admin_command_grants_access_by_email(): void
    {
        $user = $this->makeUser('cli-admin@example.com');

        $this->artisan('laravelai:make-admin', ['email' => 'cli-admin@example.com'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('ai_admins', ['user_id' => $user->id]);
    }

    public function test_make_admin_command_is_idempotent(): void
    {
        $user = $this->makeUser('twice@example.com');

        $this->artisan('laravelai:make-admin', ['email' => 'twice@example.com'])->assertExitCode(0);
        $this->artisan('laravelai:make-admin', ['email' => 'twice@example.com'])->assertExitCode(0);

        $this->assertSame(1, AiAdmin::where('user_id', $user->id)->count());
    }

    public function test_make_admin_command_fails_cleanly_for_unknown_email(): void
    {
        $this->artisan('laravelai:make-admin', ['email' => 'nobody@nowhere.com'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('ai_admins', 0);
    }

    public function test_admin_can_add_another_admin_by_email(): void
    {
        $admin = $this->makeUser('existing-admin@example.com');
        AiAdmin::create(['user_id' => $admin->id]);
        $newAdmin = $this->makeUser('promoted@example.com');

        $this->actingAs($admin)
            ->post('/ai-chat/settings/admins', ['email' => 'promoted@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_admins', ['user_id' => $newAdmin->id]);
    }

    public function test_adding_an_unregistered_email_does_not_crash_and_creates_no_row(): void
    {
        $admin = $this->makeUser('existing-admin-2@example.com');
        AiAdmin::create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->post('/ai-chat/settings/admins', ['email' => 'never-registered@example.com'])
            ->assertRedirect();

        $this->assertDatabaseCount('ai_admins', 1);
    }

    public function test_admin_can_remove_another_admin(): void
    {
        $admin = $this->makeUser('remover@example.com');
        $adminRow = AiAdmin::create(['user_id' => $admin->id]);
        $other = $this->makeUser('removable@example.com');
        $otherRow = AiAdmin::create(['user_id' => $other->id]);

        $this->actingAs($admin)
            ->delete("/ai-chat/settings/admins/{$otherRow->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('ai_admins', ['id' => $otherRow->id]);
        $this->assertDatabaseHas('ai_admins', ['id' => $adminRow->id]);
    }

    public function test_cannot_remove_the_last_remaining_admin(): void
    {
        $admin = $this->makeUser('only-admin@example.com');
        $adminRow = AiAdmin::create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->delete("/ai-chat/settings/admins/{$adminRow->id}")
            ->assertRedirect();

        // Still there — the guard refused the deletion.
        $this->assertDatabaseHas('ai_admins', ['id' => $adminRow->id]);
    }
}
