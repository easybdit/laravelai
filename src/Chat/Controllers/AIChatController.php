<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use EasyAI\LaravelAI\Chat\Models\ChatAttachment;
use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Chat\Support\ChatGuard;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Support\MessageFormatter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AIChatController extends Controller
{
    private const BUILT_IN_PROVIDERS = [
        'ollama'    => ['label' => 'Ollama (Local)',     'icon' => '🖥️'],
        'openai'    => ['label' => 'OpenAI (ChatGPT)',   'icon' => '🟢'],
        'anthropic' => ['label' => 'Anthropic (Claude)', 'icon' => '🟠'],
        'deepseek'  => ['label' => 'DeepSeek',           'icon' => '🔵'],
        'gemini'    => ['label' => 'Google Gemini',      'icon' => '✦'],
        'together'  => ['label' => 'Together AI',        'icon' => '🟣'],
    ];

    /** Providers with real image/vision understanding wired up. */
    private const VISION_PROVIDERS = ['openai', 'anthropic', 'gemini'];

    /** Built-in providers configured (have a key/URL) plus every named custom endpoint. */
    private function availableProviders(): array
    {
        $providers = self::BUILT_IN_PROVIDERS;
        foreach (config('ai.custom_providers', []) as $slug => $cfg) {
            $providers["custom:{$slug}"] = ['label' => $cfg['label'] ?? $slug, 'icon' => '🔧'];
        }
        return $providers;
    }

    private function activeProvider(Request $request): string
    {
        $providers = $this->availableProviders();
        $provider  = $request->session()->get('ai_provider', config('ai.default', 'ollama'));
        return array_key_exists($provider, $providers) ? $provider : array_key_first($providers);
    }

    private function modelFor(string $provider): ?string
    {
        if (str_starts_with($provider, 'custom:')) {
            return config('ai.custom_providers.' . substr($provider, 7) . '.model');
        }
        return config("ai.providers.{$provider}.model");
    }

    private function resolveProfile(?string $key): ?array
    {
        return $key ? config("ai.chat.bot_profiles.{$key}") : null;
    }

    private function scopeToIdentity($query, ?int $userId, ?string $guestToken)
    {
        return $query->where(function ($q) use ($userId, $guestToken) {
            if ($userId) {
                $q->where('user_id', $userId);
            } else {
                $q->where('guest_token', $guestToken);
            }
            // Sessions created before identity scoping existed (both columns
            // null) still show up rather than silently vanishing on upgrade.
            $q->orWhere(function ($q2) {
                $q2->whereNull('user_id')->whereNull('guest_token');
            });
        });
    }

    public function index(Request $request)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);

        $sessionsQuery = ChatSession::with('project');
        $this->scopeToIdentity($sessionsQuery, $userId, $guestToken);
        $sessions = $sessionsQuery->latest()->get();

        $projects      = Project::withCount('files')->latest()->get();
        $activeSession = null;
        $messages      = collect();

        if ($request->has('session')) {
            $activeSession = ChatSession::with(['messages.attachments', 'project'])->find($request->session);
            if ($activeSession && !$activeSession->isOwnedBy($userId, $guestToken)) {
                $activeSession = null;
            }
            $messages = $activeSession?->messages ?? collect();
        }

        return view('laravelai::chat', [
            'sessions'            => $sessions,
            'activeSession'       => $activeSession,
            'messages'            => $messages,
            'providers'           => $this->availableProviders(),
            'activeProvider'      => $this->activeProvider($request),
            'projects'            => $projects,
            'ui'                  => config('ai.chat.ui'),
            'gdpr'                => config('ai.chat.gdpr'),
            'captchaEnabled'      => (bool) config('ai.chat.captcha.enabled', false),
            'attachmentsEnabled'  => (bool) config('ai.chat.attachments.enabled', false),
            'disableStorage'      => (bool) config('ai.chat.disable_storage', false),
        ]);
    }

    public function captcha()
    {
        return response()->json(ChatGuard::generateCaptcha());
    }

    public function switchProvider(Request $request)
    {
        $available = array_keys($this->availableProviders());
        $request->validate(['provider' => 'required|string|in:' . implode(',', $available)]);
        $request->session()->put('ai_provider', $request->string('provider')->toString());
        return response()->json(['provider' => $request->provider, 'ok' => true]);
    }

    public function newSession(Request $request)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $guestToken = ChatIdentity::ensureGuestToken($guestToken);

        try {
            ChatGuard::enforceAccess($request);
        } catch (ChatBlockedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $projectId  = $request->input('project_id');
        $profileKey = $request->input('profile');
        $profile    = $this->resolveProfile($profileKey);

        $session = ChatSession::create([
            'title'       => 'New Chat',
            'project_id'  => $projectId ?: null,
            'user_id'     => $userId,
            'guest_token' => $userId ? null : $guestToken,
            'provider'    => $profile['provider'] ?? null,
            'profile'     => $profileKey ?: null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['session' => $session]);
        }

        return redirect()->route('ai-chat.index', ['session' => $session->id]);
    }

    public function deleteSession(Request $request, ChatSession $session)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        if (!$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $session->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('ai-chat.index');
    }

    public function feedback(Request $request, ChatMessage $message)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $session = $message->session;
        if (!$session || !$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $request->validate(['rating' => 'required|in:1,-1']);
        $message->update(['rating' => (int) $request->input('rating')]);

        return response()->json(['ok' => true, 'rating' => $message->rating]);
    }

    /**
     * Extracted document text is appended to the outgoing message (works
     * with every provider); the first attached image becomes a multipart
     * vision block for VISION_PROVIDERS and a "can't view images" note for
     * everyone else — same shape the WordPress plugin's engine settled on.
     *
     * @return array{0: string, 1: array|null, 2: int[]} [$storedMessage, $liveContent, $attachmentIds]
     */
    private function resolveAttachments(Request $request, ChatSession $session, string $provider, string $message): array
    {
        $raw = $request->input('attachment_ids');
        $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $ids = array_slice(array_filter(array_map('intval', (array) $ids)), 0, 6);

        if (empty($ids)) {
            return [$message, null, []];
        }

        $attachments = ChatAttachment::whereIn('id', $ids)
            ->where('chat_session_id', $session->id)
            ->where('status', 'ready')
            ->get();

        $storedMessage = $message;
        $attachmentIds = [];
        $primaryImage  = null;

        foreach ($attachments as $att) {
            $attachmentIds[] = $att->id;

            if ($att->type === 'document') {
                if ($att->extracted_text) {
                    $storedMessage .= "\n\n[Attached document: {$att->original_name}]\n{$att->extracted_text}";
                }
                continue;
            }

            if (!$primaryImage && in_array($provider, self::VISION_PROVIDERS, true)) {
                $path = Storage::disk('local')->path($att->stored_path);
                if (file_exists($path)) {
                    $primaryImage = ['path' => $path, 'mime' => $att->mime_type];
                    continue;
                }
            }

            $storedMessage .= "\n\n[Attached image: {$att->original_name} — this AI provider cannot view images]";
        }

        $liveContent = $primaryImage
            ? MessageFormatter::withImage($storedMessage, base64_encode(file_get_contents($primaryImage['path'])), $primaryImage['mime'])
            : null;

        return [$storedMessage, $liveContent, $attachmentIds];
    }

    private function generateTitle(string $provider, string $firstMessage): string
    {
        try {
            $prompt = 'Create a short 4-6 word title for a chat that starts with: "'
                . mb_substr($firstMessage, 0, 200)
                . '". Reply with the title only — no quotes, no trailing punctuation.';

            $raw   = AI::provider($provider)->chat([['role' => 'user', 'content' => $prompt]])->content;
            $title = trim(strip_tags((string) $raw));
            $title = trim($title, "\"'“”«» \t\n\r");

            return $title !== '' ? mb_substr($title, 0, 80) : mb_substr($firstMessage, 0, 50);
        } catch (\Throwable) {
            return mb_substr($firstMessage, 0, 50);
        }
    }

    /**
     * Together AI's FLUX image generation, reached via a "/image {prompt}"
     * or "/img {prompt}" command — mirrors the WordPress plugin's routing.
     * Only active when config('ai.providers.together.image_enabled') is on.
     */
    private function streamImageGeneration(ChatSession $session, string $prompt, bool $disableStorage, bool $isFirstMessage)
    {
        return response()->stream(function () use ($session, $prompt, $disableStorage, $isFirstMessage) {
            // Some shared hosts (and Apache's mod_deflate) wrap the response
            // in their own output buffer that silently defeats streaming
            // unless it's closed first. Skipped under the test runner —
            // TestResponse::streamedContent() manages its own capture
            // buffer, which this loop would otherwise close out from under it.
            if (!app()->runningUnitTests()) {
                for ($levels = ob_get_level(); $levels > 0; $levels--) {
                    @ob_end_clean();
                }
            }

            try {
                $url = AI::provider('together')->generateImage($prompt);
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => 'Image generation failed: ' . $e->getMessage()]) . "\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
                return;
            }

            $markdown = "![{$prompt}]({$url})";
            echo "data: " . json_encode(['text' => $markdown]) . "\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();

            $newTitle = null;
            if (!$disableStorage) {
                ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => '/image ' . $prompt]);
                $assistantMsg = ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $markdown]);
                echo "data: " . json_encode(['assistant_id' => $assistantMsg->id]) . "\n\n";

                if ($isFirstMessage) {
                    $newTitle = mb_substr($prompt, 0, 60);
                    $session->update(['title' => $newTitle]);
                }
            }

            if ($newTitle) {
                echo "data: " . json_encode(['title' => $newTitle]) . "\n\n";
            }
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function fireWebhook(ChatSession $session, string $userMessage, string $aiReply, string $provider): void
    {
        $url = config('ai.chat.webhook.url');
        if (!$url) {
            return;
        }

        $payload = [
            'session_id'   => $session->id,
            'user_message' => $userMessage,
            'ai_response'  => $aiReply,
            'provider'     => $provider,
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

    public function stream(Request $request)
    {
        $isRegenerate   = $request->boolean('regenerate');
        $disableStorage = (bool) config('ai.chat.disable_storage', false);

        $request->validate([
            'message'    => $isRegenerate ? 'nullable|string' : 'required|string',
            'session_id' => 'required|integer|exists:chat_sessions,id',
        ]);

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $guestToken = ChatIdentity::ensureGuestToken($guestToken);
        $ip = (string) $request->ip();

        $session = ChatSession::with('messages')->findOrFail($request->integer('session_id'));
        if (!$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $message = trim((string) $request->input('message', ''));

        try {
            ChatGuard::enforceIpBlocklist($ip);
            ChatGuard::enforceAccess($request);
            ChatGuard::enforceRateLimit(ChatIdentity::rateLimitKey($userId, $guestToken), $ip);

            if (!$isRegenerate) {
                ChatGuard::enforceMessageLength($message);
                ChatGuard::enforceWordFilter($message);
                ChatGuard::enforcePromptInjection($message);

                if (config('ai.chat.captcha.enabled', false) && $session->messages->isEmpty()) {
                    ChatGuard::enforceCaptcha($request->input('cap_token'), (int) $request->input('cap_answer', -1));
                }
            }
        } catch (ChatBlockedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $provider = $session->provider ?: $this->activeProvider($request);
        $profile  = $this->resolveProfile($session->profile);

        if (!$isRegenerate && config('ai.providers.together.image_enabled', false)
            && preg_match('/^\/(?:image|img)\s+(.+)$/is', $message, $imageMatch)) {
            return $this->streamImageGeneration($session, trim($imageMatch[1]), $disableStorage, $session->messages->isEmpty());
        }

        if ($isRegenerate) {
            $session->messages->where('role', 'assistant')->last()?->delete();
            $session->unsetRelation('messages')->load('messages');
        }

        $isFirstMessage = $session->messages->isEmpty();
        $storedMessage  = $message;
        $liveContent    = null;
        $attachmentIds  = [];

        if (!$isRegenerate && config('ai.chat.attachments.enabled', false) && $request->filled('attachment_ids')) {
            [$storedMessage, $liveContent, $attachmentIds] = $this->resolveAttachments($request, $session, $provider, $message);
        }

        $newTitle = null;

        if (!$isRegenerate) {
            if ($disableStorage) {
                $session->setRelation('messages', $session->messages->push(
                    new ChatMessage(['role' => 'user', 'content' => $storedMessage])
                ));
            } else {
                $userMsg = ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role'            => 'user',
                    'content'         => $storedMessage,
                ]);
                if ($attachmentIds) {
                    ChatAttachment::whereIn('id', $attachmentIds)->update(['chat_message_id' => $userMsg->id]);
                }
                if ($isFirstMessage && $session->title === 'New Chat') {
                    $newTitle = $this->generateTitle($provider, $message);
                    $session->update(['title' => $newTitle]);
                }
                $session->unsetRelation('messages')->load('messages');
            }
        }

        $history = $session->toAIMessages();

        $ctxLimit = max(1, (int) config('ai.chat.context_messages', 10));
        if (count($history) > $ctxLimit) {
            $history = array_slice($history, -$ctxLimit);
        }

        // Swap in the multipart vision content for the outgoing API call only — the
        // stored/rendered text stays plain so history replays don't need special-casing.
        if ($liveContent !== null && !empty($history)) {
            $history[array_key_last($history)]['content'] = $liveContent;
        }

        // System prompt: a locked config always wins; otherwise profile → per-request
        // override → global default.
        if (config('ai.chat.lock_system_prompt', false)) {
            $systemPrompt = $profile['system_prompt'] ?? config('ai.chat.system_prompt');
        } else {
            $clientSystem = trim((string) $request->input('system', ''));
            $systemPrompt = $clientSystem !== ''
                ? mb_substr($clientSystem, 0, 500)
                : ($profile['system_prompt'] ?? config('ai.chat.system_prompt'));
        }

        // RAG context injection — only if the project has ingested documents.
        if ($session->project_id) {
            $source   = 'project_' . $session->project_id;
            $docCount = DB::table(config('ai.rag.table', 'ai_documents'))->where('source', $source)->count();

            if ($docCount > 0) {
                try {
                    $context = AI::rag()->source($source)->search($message ?: (string) ($history[array_key_last($history)]['content'] ?? ''));

                    if (!empty($context)) {
                        $maxChunkLen = config('ai.rag.max_chunk_display', 800);
                        $contextText = collect($context)
                            ->pluck('content')
                            ->map(function ($c) use ($maxChunkLen) {
                                $c = mb_substr($c, 0, $maxChunkLen);
                                $c = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $c);
                                $c = preg_replace('/\s{2,}/', ' ', $c);
                                $c = str_replace(['<', '>'], ['[', ']'], $c);
                                return trim($c);
                            })
                            ->join(' | ');

                        $systemPrompt = config('ai.rag.system_prompt', 'Answer using ONLY the context below. If unsure, say so.')
                            . "\n\nCONTEXT:\n" . $contextText
                            . ($systemPrompt ? "\n\n" . $systemPrompt : '');
                    }
                } catch (\Throwable $e) {
                    // RAG failure non-fatal
                }
            }
        } elseif (!empty(config('ai.rag.auto_index', []))) {
            // No project attached to this session — fall back to whatever
            // AutoIndexer has indexed from the host app's own models
            // (the "Ask This Site" equivalent).
            try {
                $context = AI::rag()->searchAutoIndexed($message ?: (string) ($history[array_key_last($history)]['content'] ?? ''));
                if (!empty($context)) {
                    $contextText = collect($context)->pluck('content')
                        ->map(fn ($c) => trim(preg_replace('/\s{2,}/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', mb_substr($c, 0, 800)))))
                        ->join(' | ');
                    $systemPrompt = config('ai.rag.system_prompt', 'Answer using ONLY the context below. If unsure, say so.')
                        . "\n\nCONTEXT:\n" . $contextText
                        . ($systemPrompt ? "\n\n" . $systemPrompt : '');
                }
            } catch (\Throwable $e) {
                // RAG failure non-fatal
            }
        }

        $chatModel = $this->modelFor($provider);

        return response()->stream(function () use (
            $session, $history, $provider, $chatModel, $systemPrompt,
            $disableStorage, $newTitle, $message, $request
        ) {
            $fullReply = '';

            // Some shared hosts (and Apache's mod_deflate) wrap the response
            // in their own output buffer that silently defeats streaming
            // unless it's closed first. Skipped under the test runner —
            // TestResponse::streamedContent() manages its own capture
            // buffer, which this loop would otherwise close out from under it.
            if (!app()->runningUnitTests()) {
                for ($levels = ob_get_level(); $levels > 0; $levels--) {
                    @ob_end_clean();
                }
            }

            try {
                AI::provider($provider)
                    ->model($chatModel)
                    ->systemPrompt((string) $systemPrompt)
                    ->timeout(300)
                    ->stream(
                        $history,
                        function (string $chunk) use (&$fullReply) {
                            $fullReply .= $chunk;
                            echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }
                    );
            } catch (ConnectionException $e) {
                echo "data: " . json_encode(['error' => ChatGuard::publicErrorMessage($e, $request)]) . "\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => ChatGuard::publicErrorMessage($e, $request)]) . "\n\n";
            }

            if ($fullReply && !$disableStorage) {
                $assistantMsg = ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role'            => 'assistant',
                    'content'         => $fullReply,
                ]);
                echo "data: " . json_encode(['assistant_id' => $assistantMsg->id]) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();

                $this->fireWebhook($session, $message, $fullReply, $provider);
            }

            if ($newTitle) {
                echo "data: " . json_encode(['title' => $newTitle]) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
