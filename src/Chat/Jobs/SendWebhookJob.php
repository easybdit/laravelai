<?php

namespace EasyAI\LaravelAI\Chat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Queued counterpart to AIChatController::fireWebhook() — dispatched
 * instead of calling Http::post() inline when
 * config('ai.chat.webhook.queue') is true, so the SSE stream's [DONE]
 * event isn't delayed by however long the webhook endpoint takes to
 * respond (up to the 5s timeout below).
 *
 * Takes primitives ($sessionId, $userMessage, $aiReply, $provider) rather
 * than the ChatSession model, exactly mirroring fireWebhook()'s existing
 * $payload shape — keeps the serialized job small and avoids re-fetching a
 * model that isn't needed for anything beyond its id.
 *
 * This is already fire-and-forget today (fireWebhook() never surfaces the
 * webhook's response to the user), so queueing it is a pure win with no
 * behavior to preserve beyond the config flag itself.
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $sessionId,
        public string $userMessage,
        public string $aiReply,
        public string $provider
    ) {
    }

    public function handle(): void
    {
        $url = config('ai.chat.webhook.url');
        if (!$url) {
            return;
        }

        $payload = [
            'session_id'   => $this->sessionId,
            'user_message' => $this->userMessage,
            'ai_response'  => $this->aiReply,
            'provider'     => $this->provider,
            'timestamp'    => now()->toIso8601String(),
            'app_url'      => config('app.url'),
        ];

        $headers = [];
        if ($secret = config('ai.chat.webhook.secret')) {
            $headers['X-LaravelAI-Signature'] = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
        }

        try {
            Http::timeout(5)->withHeaders($headers)->post($url, $payload);
        } catch (\Throwable) {
            // Best-effort — a broken webhook endpoint should never surface as a chat error.
        }
    }
}
