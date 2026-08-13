<?php

namespace EasyAI\LaravelAI\Commerce\Controllers;

use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use EasyAI\LaravelAI\Chat\Support\ChatGuard;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Commerce\Contracts\OrderResolver;
use EasyAI\LaravelAI\Commerce\Support\StorePrompts;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Order-status chat. Logged-in users are resolved by their own user id
 * (never someone else's order); guests must supply the order number AND
 * the billing email, verified by OrderResolver — never trusted from the
 * request alone.
 */
class OrderStatusController extends Controller
{
    public function ask(Request $request)
    {
        if (!app()->bound(OrderResolver::class)) {
            return response()->json([
                'error' => 'Order status assistant is not configured. Bind '
                    . OrderResolver::class . ' in your app\'s service provider.',
            ], 501);
        }

        $request->validate(['question' => 'required|string|max:500']);
        $question = trim($request->input('question'));

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        try {
            ChatGuard::enforceRateLimit(ChatIdentity::rateLimitKey($userId, $guestToken), (string) $request->ip());
            ChatGuard::enforceMessageLength($question);
        } catch (ChatBlockedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $resolver = app(OrderResolver::class);

        if ($request->user()) {
            $orders = $resolver->findForUser($request->user()->getAuthIdentifier(), $request->input('order_number'));
        } else {
            $request->validate(['order_number' => 'required|string', 'email' => 'required|email']);
            $order  = $resolver->findByNumberAndEmail($request->input('order_number'), $request->input('email'));
            $orders = $order ? [$order] : [];
        }

        if (empty($orders)) {
            return response()->json([
                'reply' => "I couldn't find that order — please double-check the order number and email.",
            ]);
        }

        $provider = config('ai.commerce.provider') ?? config('ai.default');

        try {
            $reply = AI::provider($provider)
                ->systemPrompt(StorePrompts::orderStatus() . "\n\nORDER DATA:\n" . json_encode($orders))
                ->chat([['role' => 'user', 'content' => $question]])->getContent();

            return response()->json(['reply' => $reply]);
        } catch (\Throwable $e) {
            return response()->json(['error' => ChatGuard::publicErrorMessage($e, $request)], 500);
        }
    }
}
