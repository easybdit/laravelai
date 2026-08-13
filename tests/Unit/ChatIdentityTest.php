<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Http\Request;

/**
 * Regression coverage for a real bug found in production: resolve()'s
 * cookie-format validation didn't match what ensureGuestToken() actually
 * generates, so every returning guest's cookie was silently rejected and
 * a brand new identity was minted on every single request — guest chat
 * history never persisted for anyone.
 */
class ChatIdentityTest extends TestCase
{
    public function test_a_freshly_minted_guest_token_round_trips_through_resolve(): void
    {
        $token = ChatIdentity::ensureGuestToken(null);

        $request = Request::create('/');
        $request->cookies->set(ChatIdentity::COOKIE_NAME, $token);

        [$userId, $resolvedToken] = ChatIdentity::resolve($request);

        $this->assertNull($userId);
        $this->assertSame($token, $resolvedToken, 'A token minted by ensureGuestToken() must be accepted back by resolve() on the very next request.');
    }

    public function test_ensure_guest_token_returns_the_existing_token_unchanged(): void
    {
        $existing = ChatIdentity::ensureGuestToken(null);

        $this->assertSame($existing, ChatIdentity::ensureGuestToken($existing));
    }

    public function test_resolve_rejects_a_malformed_cookie_value(): void
    {
        $request = Request::create('/');
        $request->cookies->set(ChatIdentity::COOKIE_NAME, 'not-a-real-token');

        [, $resolvedToken] = ChatIdentity::resolve($request);

        $this->assertNull($resolvedToken);
    }

    public function test_resolve_returns_null_token_with_no_cookie_at_all(): void
    {
        [$userId, $resolvedToken] = ChatIdentity::resolve(Request::create('/'));

        $this->assertNull($userId);
        $this->assertNull($resolvedToken);
    }

    /**
     * The default $request->user() check only works for classic
     * session-based auth. Apps whose /ai-chat visitor never carries a
     * Laravel session at all — a Bearer-token SPA is the common real
     * case, confirmed live: every session showed user_id = NULL despite
     * the user being genuinely signed in to the SPA — need to plug in
     * their own resolution logic instead.
     */
    public function test_a_custom_identity_resolver_overrides_the_default_user_check(): void
    {
        config(['ai.chat.identity_resolver' => fn ($request) => 42]);

        [$userId, $guestToken] = ChatIdentity::resolve(Request::create('/'));

        $this->assertSame(42, $userId);
        $this->assertNull($guestToken, 'A resolved user id should never also carry a guest token.');
    }

    public function test_a_custom_identity_resolver_returning_null_falls_back_to_guest(): void
    {
        config(['ai.chat.identity_resolver' => fn ($request) => null]);

        [$userId] = ChatIdentity::resolve(Request::create('/'));

        $this->assertNull($userId);
    }

    public function test_a_throwing_identity_resolver_degrades_to_guest_rather_than_crashing(): void
    {
        config(['ai.chat.identity_resolver' => function () {
            throw new \RuntimeException('host app resolver blew up');
        }]);

        [$userId] = ChatIdentity::resolve(Request::create('/'));

        $this->assertNull($userId, 'A misbehaving host resolver must degrade to guest, not break the whole chat request.');
    }

    public function test_resolve_display_name_returns_null_when_a_custom_resolver_is_configured(): void
    {
        config(['ai.chat.identity_resolver' => fn ($request) => 1]);

        $this->assertNull(ChatIdentity::resolveDisplayName(Request::create('/')));
    }
}
