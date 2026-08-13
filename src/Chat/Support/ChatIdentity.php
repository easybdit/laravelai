<?php

namespace EasyAI\LaravelAI\Chat\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Resolves "who is chatting" for the current request — an authenticated
 * user id, or a long-lived signed guest cookie token. Mirrors the
 * WordPress plugin's guest-cookie approach so sessions never leak
 * across visitors, but uses Laravel's own signed-cookie machinery
 * instead of hand-rolled setcookie().
 */
class ChatIdentity
{
    public const COOKIE_NAME = 'laravelai_guest';

    /**
     * @return array{0: int|null, 1: string|null} [$userId, $guestToken]
     */
    public static function resolve(Request $request): array
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId) {
            return [(int) $userId, null];
        }

        if (!config('ai.chat.access.allow_guest', true)) {
            return [null, null];
        }

        $token = $request->cookie(self::COOKIE_NAME);

        // Must match ensureGuestToken()'s actual output alphabet — Str::random()
        // produces base64 with '/', '+', '=' stripped, i.e. mixed-case
        // alphanumeric, NOT lowercase hex. A mismatch here silently rejects
        // every returning guest's cookie and re-mints a new identity on every
        // single request, which is as bad as having no persistence at all.
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            $token = null;
        }

        return [null, $token];
    }

    /**
     * Issue a fresh guest token when the request didn't carry one, queuing
     * the cookie on the outgoing response. Call once per request, after
     * resolve() — pass its guest token back in; returns the token to use
     * for this request (existing or freshly minted).
     */
    public static function ensureGuestToken(?string $existing): string
    {
        if ($existing) {
            return $existing;
        }

        $token = Str::random(40);

        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $token,
            60 * 24 * 365, // 1 year
            null,
            null,
            null,
            true,   // httpOnly
            false,
            'lax'
        ));

        return $token;
    }

    public static function rateLimitKey(?int $userId, ?string $guestToken): string
    {
        return $userId ? "u:{$userId}" : 'g:' . ($guestToken ?: 'anon');
    }
}
