<?php

namespace EasyAI\LaravelAI\Chat\Support;

use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The Laravel port of the WordPress plugin's "Security Suite" (v2.0):
 * rate limiting, access restriction, IP blocklist, word filter, prompt
 * injection detection, message-length cap, math captcha, and abuse-alert
 * email. Every check throws ChatBlockedException with a message that's
 * always safe to show the caller directly.
 *
 * Reads its settings from config('ai.chat.*') — see config/ai.php.
 */
class ChatGuard
{
    /**
     * Rate-limit the current identity, then the current IP as a secondary
     * hard cap (so rotating guest cookies can't bypass the per-identity
     * limit). No-ops entirely when rate_limit.enabled is false.
     */
    public static function enforceRateLimit(string $identityKey, string $ip): void
    {
        if (!config('ai.chat.rate_limit.enabled', true)) {
            return;
        }

        $window = max(10, (int) config('ai.chat.rate_limit.window', 60));
        $max    = max(1, (int) config('ai.chat.rate_limit.max', 20));
        $ipMax  = max(1, (int) config('ai.chat.rate_limit.ip_max', 60));

        $idBucket = 'laravelai:rl:' . $identityKey;
        $ipBucket = 'laravelai:rl:ip:' . $ip;

        if (RateLimiter::tooManyAttempts($idBucket, $max) || ($ip !== '' && RateLimiter::tooManyAttempts($ipBucket, $ipMax))) {
            self::maybeSendAbuseAlert($ip);
            throw new ChatBlockedException('Too many requests. Please wait a moment.', 429);
        }

        RateLimiter::hit($idBucket, $window);
        if ($ip !== '') {
            RateLimiter::hit($ipBucket, $window);
        }
    }

    public static function enforceIpBlocklist(string $ip): void
    {
        $blocklist = config('ai.chat.ip_blocklist', []);
        if ($ip !== '' && !empty($blocklist) && in_array($ip, $blocklist, true)) {
            throw new ChatBlockedException('Access denied.', 403);
        }
    }

    /**
     * everyone | auth | role | gate — see config('ai.chat.access.*').
     * "role" looks for a $user->hasAnyRole([...]) style method (configurable
     * name, defaults to the common Spatie permission convention) so this
     * doesn't hard-depend on any particular roles package.
     */
    public static function enforceAccess(Request $request): void
    {
        $restriction = config('ai.chat.access.restriction', 'everyone');
        if ($restriction === 'everyone') {
            return;
        }

        $user = $request->user();

        if (!$user) {
            throw new ChatBlockedException('You must be logged in to use this chat.', 403);
        }

        if ($restriction === 'auth') {
            return;
        }

        if ($restriction === 'gate') {
            $gate = config('ai.chat.access.gate', 'use-ai-chat');
            if (!\Illuminate\Support\Facades\Gate::forUser($user)->allows($gate)) {
                throw new ChatBlockedException('You do not have permission to use this chat.', 403);
            }
            return;
        }

        if ($restriction === 'role') {
            $roles  = config('ai.chat.access.roles', []);
            $method = config('ai.chat.access.role_method', 'hasAnyRole');

            $allowed = false;
            if (!empty($roles) && method_exists($user, $method)) {
                $allowed = (bool) $user->{$method}($roles);
            } elseif (!empty($roles) && isset($user->roles)) {
                $userRoles = $user->roles instanceof \Illuminate\Support\Collection
                    ? $user->roles->pluck('name')->all()
                    : (array) $user->roles;
                $allowed = (bool) array_intersect($roles, $userRoles);
            }

            if (!$allowed) {
                throw new ChatBlockedException('You do not have permission to use this chat.', 403);
            }
        }
    }

    public static function enforceMessageLength(string $message): void
    {
        $max = max(50, (int) config('ai.chat.max_message_length', 4000));
        if (mb_strlen($message) > $max) {
            throw new ChatBlockedException("Message too long. Maximum {$max} characters.", 400);
        }
        if (trim($message) === '') {
            throw new ChatBlockedException('Empty message.', 400);
        }
    }

    public static function enforceWordFilter(string $message): void
    {
        if (!config('ai.chat.word_filter.enabled', false)) {
            return;
        }
        $banned = config('ai.chat.word_filter.words', []);
        foreach ($banned as $word) {
            if ($word !== '' && mb_stripos($message, $word) !== false) {
                $warn = config('ai.chat.word_filter.action', 'block') === 'warn';
                throw new ChatBlockedException(
                    $warn ? 'Your message contains a word that is not allowed.' : 'Message could not be sent.',
                    400
                );
            }
        }
    }

    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',
        '/you\s+are\s+now\s+(a\s+)?/i',
        '/act\s+as\s+(if\s+(you\s+are|you\'re)\s+)?a\s+/i',
        '/disregard\s+(all\s+)?(your|previous|prior)\s+/i',
        '/forget\s+(everything|all|your\s+instructions)/i',
        '/jailbreak/i',
        '/DAN\s+mode/i',
        '/\[SYSTEM\]/i',
        '/pretend\s+(you\s+are|to\s+be)\s+/i',
        '/override\s+(your\s+)?(safety|instructions|guidelines)/i',
    ];

    public static function enforcePromptInjection(string $message): void
    {
        if (!config('ai.chat.prompt_injection.enabled', false)) {
            return;
        }
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                $warn = config('ai.chat.prompt_injection.action', 'block') === 'warn';
                throw new ChatBlockedException(
                    $warn ? 'Suspicious message pattern detected. Please rephrase.' : 'Message could not be sent.',
                    400
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // Math captcha — signed, stateless (no server-side session store
    // required), solved once per browser session on the client.
    // -----------------------------------------------------------------

    public static function generateCaptcha(): array
    {
        $a   = random_int(1, 9);
        $b   = random_int(1, 9);
        $ans = $a + $b;
        $exp = time() + 600;
        $data = "{$a}|{$b}|{$ans}|{$exp}";
        $sig  = hash_hmac('sha256', $data, config('app.key'));

        return ['question' => "{$a} + {$b}", 'token' => $data . '|' . $sig];
    }

    public static function enforceCaptcha(?string $token, ?int $answer): void
    {
        if (!config('ai.chat.captcha.enabled', false)) {
            return;
        }
        if (!self::verifyCaptcha((string) $token, (int) $answer)) {
            throw new ChatBlockedException('Incorrect captcha answer. Please try again.', 400);
        }
    }

    private static function verifyCaptcha(string $token, int $answer): bool
    {
        if ($token === '') {
            return false;
        }
        $parts = explode('|', $token);
        if (count($parts) !== 5) {
            return false;
        }
        [$a, $b, $storedAns, $exp, $sig] = $parts;
        if (time() > (int) $exp) {
            return false;
        }
        $expected = hash_hmac('sha256', "{$a}|{$b}|{$storedAns}|{$exp}", config('app.key'));
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        return $answer === (int) $storedAns;
    }

    // -----------------------------------------------------------------

    public static function maybeSendAbuseAlert(string $ip): void
    {
        if (!config('ai.chat.abuse_alert.enabled', false) || $ip === '') {
            return;
        }

        $cacheKey = 'laravelai:abuse-alerted:' . md5($ip);
        if (Cache::has($cacheKey)) {
            return; // already alerted recently
        }
        Cache::put($cacheKey, true, max(10, (int) config('ai.chat.rate_limit.window', 60)));

        $to = config('ai.chat.abuse_alert.email') ?: config('mail.from.address');
        if (!$to) {
            return;
        }

        try {
            Mail::raw(
                "The IP address {$ip} has exceeded the chat rate limit.\n\nTime: " . now()->toDateTimeString()
                . "\n\nAdd this IP to AI_CHAT_IP_BLOCKLIST in .env if this continues.",
                function ($message) use ($to) {
                    $message->to($to)->subject('[' . config('app.name') . '] Chat Rate Limit Exceeded');
                }
            );
        } catch (\Throwable) {
            // Non-fatal — a broken mail config should never break the chat.
        }
    }

    /**
     * What the caller is allowed to see when something throws mid-request.
     * Admins (however the host app defines "can see internals" — via the
     * configured gate, or simply local/debug) get the real message;
     * everyone else gets a message that can't leak configuration details
     * (a missing API key, a provider's raw error body, ...).
     */
    public static function publicErrorMessage(\Throwable $e, Request $request): string
    {
        if (config('ai.chat.show_internal_errors') === true) {
            return $e->getMessage();
        }
        if (config('ai.chat.show_internal_errors') === null && config('app.debug')) {
            return $e->getMessage();
        }

        $gate = config('ai.chat.access.gate', 'use-ai-chat');
        if ($request->user() && \Illuminate\Support\Facades\Gate::forUser($request->user())->allows($gate)) {
            return $e->getMessage();
        }

        return 'Sorry, the assistant is temporarily unavailable. Please try again later.';
    }
}
