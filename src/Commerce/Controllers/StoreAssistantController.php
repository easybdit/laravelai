<?php

namespace EasyAI\LaravelAI\Commerce\Controllers;

use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use EasyAI\LaravelAI\Chat\Support\ChatGuard;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Commerce\Contracts\StoreAnalyticsResolver;
use EasyAI\LaravelAI\Commerce\Support\StorePrompts;
use EasyAI\LaravelAI\Commerce\Support\StructuredResponseParser;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * "Ask Your Store" — an admin-only natural-language chat over aggregate
 * store data. Never queries a database itself; every number comes from
 * whatever StoreAnalyticsResolver implementation the host app binds.
 *
 * Access is fail-closed: config('ai.commerce.gate') must be explicitly
 * defined by the host app (Gate::define(...)) or every request is refused,
 * regardless of auth state — there is no "everyone" mode for this one, on
 * purpose. This mirrors the WordPress plugin requiring Shop Manager/Admin.
 */
class StoreAssistantController extends Controller
{
    public function ask(Request $request)
    {
        if (!$request->user() || !Gate::forUser($request->user())->allows(config('ai.commerce.gate', 'view-store-assistant'))) {
            abort(403, 'You do not have permission to use the store assistant.');
        }

        if (!app()->bound(StoreAnalyticsResolver::class)) {
            return response()->json([
                'error' => 'Store assistant is not configured. Bind '
                    . StoreAnalyticsResolver::class . ' in your app\'s service provider.',
            ], 501);
        }

        $request->validate(['question' => 'required|string|max:1000']);
        $question = trim($request->input('question'));

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        try {
            ChatGuard::enforceRateLimit(ChatIdentity::rateLimitKey($userId, $guestToken), (string) $request->ip());
            ChatGuard::enforceMessageLength($question);
        } catch (ChatBlockedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $resolver   = app(StoreAnalyticsResolver::class);
        $provider   = config('ai.commerce.provider') ?? config('ai.default');
        $systemBase = StorePrompts::analytics(config('app.name', 'the store'));

        try {
            $raw = AI::provider($provider)->systemPrompt($systemBase)
                ->chat([['role' => 'user', 'content' => $question]])->getContent();

            $parsed = StructuredResponseParser::extract($raw, 'query');

            if ($parsed['data'] === null) {
                return response()->json(['answer' => $parsed['text']]);
            }

            $data  = $this->executeQuery($resolver, $parsed['data']);
            $final = AI::provider($provider)
                ->systemPrompt($systemBase . "\n\nDATA:\n" . json_encode($data))
                ->chat([['role' => 'user', 'content' => $question]])->getContent();

            return response()->json(['answer' => StructuredResponseParser::extract($final, 'query')['text']]);
        } catch (\Throwable $e) {
            return response()->json(['error' => ChatGuard::publicErrorMessage($e, $request)], 500);
        }
    }

    private function executeQuery(StoreAnalyticsResolver $resolver, array $query): array
    {
        $type  = $query['type'] ?? 'summary';
        $from  = $query['from'] ?? now()->subDays(30)->format('Y-m-d');
        $to    = $query['to'] ?? now()->format('Y-m-d');
        $limit = min(20, max(1, (int) ($query['limit'] ?? 5)));

        return match ($type) {
            'revenue'       => $resolver->revenue($from, $to),
            'orders'        => $resolver->orders($from, $to),
            'top_products'  => $resolver->topProducts($from, $to, $limit),
            'low_stock'     => $resolver->lowStock(5, $limit),
            'new_customers' => $resolver->newCustomers($from, $to, $limit),
            default         => $resolver->summary(),
        };
    }
}
