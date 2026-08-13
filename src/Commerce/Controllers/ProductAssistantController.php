<?php

namespace EasyAI\LaravelAI\Commerce\Controllers;

use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use EasyAI\LaravelAI\Chat\Support\ChatGuard;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Commerce\Contracts\ProductResolver;
use EasyAI\LaravelAI\Commerce\Support\StorePrompts;
use EasyAI\LaravelAI\Commerce\Support\StructuredResponseParser;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Customer-facing product Q&A / "smart finder" — one AI call extracts
 * search criteria + a short reply, this controller runs the actual search
 * through ProductResolver and hands both back to the client to render.
 */
class ProductAssistantController extends Controller
{
    public function ask(Request $request)
    {
        if (!app()->bound(ProductResolver::class)) {
            return response()->json([
                'error' => 'Product assistant is not configured. Bind '
                    . ProductResolver::class . ' in your app\'s service provider.',
            ], 501);
        }

        $request->validate(['question' => 'required|string|max:500']);
        $question = trim($request->input('question'));

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        try {
            ChatGuard::enforceAccess($request);
            ChatGuard::enforceRateLimit(ChatIdentity::rateLimitKey($userId, $guestToken), (string) $request->ip());
            ChatGuard::enforceMessageLength($question);
            ChatGuard::enforceWordFilter($question);
            ChatGuard::enforcePromptInjection($question);
        } catch (ChatBlockedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $provider = config('ai.commerce.provider') ?? config('ai.default');

        try {
            $raw = AI::provider($provider)->systemPrompt(StorePrompts::productFinder(config('app.name', 'the store')))
                ->chat([['role' => 'user', 'content' => $question]])->getContent();

            $parsed   = StructuredResponseParser::extract($raw, 'search');
            $products = [];

            if ($parsed['data'] !== null) {
                $limit    = max(1, (int) config('ai.commerce.product_search_limit', 4));
                $products = array_slice(app(ProductResolver::class)->search($parsed['data']), 0, $limit);
            }

            return response()->json(['reply' => $parsed['text'], 'products' => $products]);
        } catch (\Throwable $e) {
            return response()->json(['error' => ChatGuard::publicErrorMessage($e, $request)], 500);
        }
    }
}
