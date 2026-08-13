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
}
