<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Agent\Tools\WebSearchTool;
use EasyAI\LaravelAI\Chat\Exceptions\ChatBlockedException;
use EasyAI\LaravelAI\Chat\Jobs\SendWebhookJob;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AIChatController extends Controller
{
    private const BUILT_IN_PROVIDERS = [
        'ollama'    => ['label' => 'Ollama (Local)',     'icon' => '🖥️'],
        'openai'    => ['label' => 'OpenAI (ChatGPT)',   'icon' => '🟢'],
        'anthropic' => ['label' => 'Anthropic (Claude)', 'icon' => '🟠'],
        'deepseek'  => ['label' => 'DeepSeek',           'icon' => '🔵'],
        'groq'      => ['label' => 'Groq',               'icon' => '⚡'],
        'gemini'    => ['label' => 'Google Gemini',      'icon' => '✦'],
        'together'  => ['label' => 'Together AI',        'icon' => '🟣'],
    ];

    /** Providers with real image/vision understanding wired up. */
    private const VISION_PROVIDERS = ['openai', 'anthropic', 'gemini'];

    /**
     * Providers with a real generateImage() implementation, in fallback
     * preference order (see resolveImageProvider()). Together was first to
     * ship here (FLUX) and stays first in the fallback list so existing
     * together.image_enabled-only setups keep behaving exactly as before;
     * openai's dall-e-3/gpt-image-1 support already existed on the driver
     * but was never reachable from the chat UI's /image command until
     * openai.image_enabled shipped; gemini's "Nano Banana" model family is
     * the newest addition. The other built-in providers (anthropic,
     * deepseek, groq, ollama) genuinely have no image-generation API at
     * all — not a gap to close, just not something those providers offer.
     */
    private const IMAGE_PROVIDERS = ['together', 'openai', 'gemini'];

    /**
     * Which provider should actually handle a /image or /img command, if
     * any. Prefers the session's currently active chat provider when it's
     * also image-capable and has image_enabled on, so asking for an image
     * while chatting with OpenAI uses OpenAI's own model rather than
     * silently billing a different provider the user never selected. Falls
     * back to the first other IMAGE_PROVIDERS entry with image_enabled on,
     * so a Together-only setup keeps working unchanged no matter which
     * provider the chat itself is using. Returns null when nothing has
     * image generation enabled at all — the caller then lets the message
     * flow through as a normal chat turn, exactly like today when no
     * provider's image_enabled is on.
     */
    private function resolveImageProvider(string $activeProvider): ?string
    {
        if (in_array($activeProvider, self::IMAGE_PROVIDERS, true)
            && config("ai.providers.{$activeProvider}.image_enabled", false)) {
            return $activeProvider;
        }

        foreach (self::IMAGE_PROVIDERS as $provider) {
            if (config("ai.providers.{$provider}.image_enabled", false)) {
                return $provider;
            }
        }

        return null;
    }

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

    /**
     * Maps config('ai.chat.enabled_tools') names to ready-to-use Tool
     * instances for the chat UI's agent-loop path (config('ai.chat.tools_enabled')).
     * Only built-in tools are wireable here for now — see that config
     * key's docblock. Unknown names are silently skipped, not fatal — a
     * typo in config shouldn't break chat.
     *
     * @return \EasyAI\LaravelAI\Agent\Tool[]
     */
    private function enabledTools(): array
    {
        $tools = [];
        foreach (config('ai.chat.enabled_tools', []) as $name) {
            $tools[] = match ($name) {
                'web_search' => WebSearchTool::make(),
                default      => null,
            };
        }
        return array_values(array_filter($tools));
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
        $sidebarLimit = max(1, (int) config('ai.chat.sidebar_limit', 100));

        // Sessions and projects both stop at $sidebarLimit rather than
        // loading a user's entire history unbounded on every page view —
        // for a long-time user that could mean thousands of rows fetched
        // and rendered on every single visit. The sidebar's client-side
        // search only searches what's actually loaded, so raise
        // AI_CHAT_SIDEBAR_LIMIT if you need deeper history search.
        $sessionsQuery = ChatSession::with('project');
        $this->scopeToIdentity($sessionsQuery, $userId, $guestToken);
        $sessions = $sessionsQuery->latest()->limit($sidebarLimit)->get();

        $projectsQuery = Project::withCount('files');
        $this->scopeToIdentity($projectsQuery, $userId, $guestToken);
        $projects = $projectsQuery->latest()->limit($sidebarLimit)->get();

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
            'identifiedUserId'    => $userId,
            'identifiedUserName'  => ChatIdentity::resolveDisplayName($request),
            'canManageSettings'   => $request->user()
                && \Illuminate\Support\Facades\Gate::forUser($request->user())
                    ->allows(config('ai.chat.settings_gate', 'manage-ai-settings')),
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

        // The ai_chat_attachments *rows* cascade-delete via the FK, but their
        // actual files on disk (chat-attachments/{session_id}/...) don't —
        // a DB cascade only ever touches the database. Left alone, every
        // deleted session with attachments leaks its uploaded files
        // forever. Best-effort: a storage failure here shouldn't block the
        // user from deleting their session.
        try {
            Storage::disk('local')->deleteDirectory('chat-attachments/' . $session->id);
        } catch (\Throwable $e) {
            Log::warning('laraveleasyai: failed to clean up attachment files for deleted session', ['session_id' => $session->id, 'error' => $e->getMessage()]);
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

    private const EXPORTERS = [
        'pdf'  => \EasyAI\LaravelAI\Chat\Support\Export\PdfExporter::class,
        'docx' => \EasyAI\LaravelAI\Chat\Support\Export\DocxExporter::class,
        'xlsx' => \EasyAI\LaravelAI\Chat\Support\Export\XlsxExporter::class,
        'pptx' => \EasyAI\LaravelAI\Chat\Support\Export\PptxExporter::class,
    ];

    /**
     * Server-side conversation export — PDF (dompdf), Word (phpword), Excel
     * (phpspreadsheet), or PowerPoint (phppresentation), each an optional
     * dependency exactly like smalot/pdfparser for RAG's PDF ingestion: a
     * clear, actionable error rather than a fatal class-not-found when the
     * matching package isn't installed. Client-side plain-text export
     * (works with zero dependencies, even in no-storage mode) stays as-is
     * in the chat view.
     */
    public function export(Request $request, ChatSession $session, string $format)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        if (!$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $exporterClass = self::EXPORTERS[$format] ?? null;
        if (!$exporterClass) {
            abort(404);
        }

        if (!$exporterClass::isAvailable()) {
            $package = match ($format) {
                'pdf'   => 'dompdf/dompdf',
                'docx'  => 'phpoffice/phpword',
                'xlsx'  => 'phpoffice/phpspreadsheet',
                'pptx'  => 'phpoffice/phppresentation',
            };
            return response()->json(['error' => "Exporting as .{$format} requires: composer require {$package}"], 501);
        }

        $title    = $session->title ?: 'AI Chat Conversation';
        $messages = $session->messages()->orderBy('created_at')->get();
        $contents = $exporterClass::build($title, $messages);
        $filename = 'conversation-' . $session->id . '.' . $exporterClass::EXTENSION;

        return response($contents, 200, [
            'Content-Type'        => $exporterClass::MIME,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generates (or returns the already-existing) public share link for
     * this session — idempotent, same "repeat clicks return the same
     * result" posture as everything else on this controller. Generated
     * the same way guest_token is (Str::random(40)) but serves an
     * entirely different purpose: guest_token identifies the guest's own
     * browser for ownership checks and must never leave it; share_token
     * is deliberately meant to be handed to someone else.
     */
    public function shareLink(Request $request, ChatSession $session)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        if (!$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        if (!$session->share_token) {
            $session->update(['share_token' => Str::random(40)]);
        }

        return response()->json(['url' => route('ai-chat.share.view', $session->share_token)]);
    }

    /** Revokes a share link — the old URL 404s immediately after (publicShare() below looks the token up fresh every time, no caching). */
    public function unshareLink(Request $request, ChatSession $session)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        if (!$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $session->update(['share_token' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * The public, read-only page a share link resolves to — deliberately
     * no ChatIdentity::resolve()/isOwnedBy() check at all here, unlike
     * every other method on this controller that touches a ChatSession.
     * That's the entire point of a share link: whoever has it can view
     * the conversation, logged in or not, on this app or a different
     * browser/device entirely. Registered outside the config('ai.chat.middleware')
     * gate in ChatServiceProvider so a host app's AI_CHAT_MIDDLEWARE=auth
     * (which locks down the rest of /ai-chat) can't accidentally lock out
     * a link that was explicitly generated to be public.
     */
    public function publicShare(string $token)
    {
        $session  = ChatSession::where('share_token', $token)->firstOrFail();
        $messages = $session->messages()->orderBy('created_at')->get();

        return view('laravelai::public-share', [
            'session'  => $session,
            'messages' => $messages,
        ]);
    }

    /** Hard cap on how many images ride along as vision input on one message — a directly attached image, or a PDF's rendered pages, or both mixed together. */
    private const MAX_VISION_IMAGES = 6;

    /**
     * Extracted document text is appended to the outgoing message (works
     * with every provider); every attached image — up to
     * self::MAX_VISION_IMAGES — becomes a multipart vision block for
     * VISION_PROVIDERS, and a "can't view images" note for everyone else.
     * A PDF attachment with page images already rendered (PdfPageRenderer,
     * config('ai.chat.attachments.pdf_vision_enabled')) contributes those
     * pages the same way, on top of its own extracted text — the user only
     * ever picks the PDF itself, never its page images directly.
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
            ->with('pageImages')
            ->get();

        $storedMessage = $message;
        $attachmentIds = [];
        $isVisionProvider = in_array($provider, self::VISION_PROVIDERS, true);
        /** @var array<int, array{mime: string, data: string}> $images */
        $images = [];

        $tryAddImage = function (string $storedPath, string $mimeType) use (&$images): bool {
            if (count($images) >= self::MAX_VISION_IMAGES) {
                return false;
            }
            $path = Storage::disk('local')->path($storedPath);
            if (!file_exists($path)) {
                return false;
            }
            $images[] = ['mime' => $mimeType, 'data' => base64_encode(file_get_contents($path))];
            return true;
        };

        foreach ($attachments as $att) {
            $attachmentIds[] = $att->id;

            if ($att->type === 'document') {
                if ($att->extracted_text) {
                    $storedMessage .= "\n\n[Attached document: {$att->original_name}]\n{$att->extracted_text}";
                }
                if ($isVisionProvider) {
                    foreach ($att->pageImages as $page) {
                        $tryAddImage($page->stored_path, $page->mime_type);
                    }
                }
                continue;
            }

            if ($isVisionProvider && $tryAddImage($att->stored_path, $att->mime_type)) {
                continue;
            }

            $storedMessage .= "\n\n[Attached image: {$att->original_name} — this AI provider cannot view images]";
        }

        $liveContent = $images ? MessageFormatter::withImages($storedMessage, $images) : null;

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
     * Image generation reached via a "/image {prompt}" or "/img {prompt}"
     * chat command — mirrors the WordPress plugin's routing. $providerKey
     * is whatever resolveImageProvider() picked (Together's FLUX or
     * OpenAI's dall-e-3/gpt-image-1 today), already confirmed to have
     * image_enabled on for that provider.
     */
    private function streamImageGeneration(ChatSession $session, string $providerKey, string $prompt, bool $disableStorage, bool $isFirstMessage)
    {
        return response()->stream(function () use ($session, $providerKey, $prompt, $disableStorage, $isFirstMessage) {
            // See the same guard in stream() — image generation is also a
            // single potentially-slow external call, and the save below it
            // shouldn't be at the mercy of php.ini's max_execution_time.
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

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
                $url = AI::provider($providerKey)->generateImage($prompt);
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => 'Image generation failed: ' . $e->getMessage()]) . "\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
                return;
            }

            // Together's returned URL is a temporary pickup link, not
            // permanent hosting — confirmed expiring within a couple of
            // hours in real use (a stored chat message pointing straight at
            // it goes permanently broken once it does). When storage is on,
            // mirror the file into this app's own attachment storage
            // immediately and link to that instead — same durability as a
            // user-uploaded image. Falls back to the raw URL (today's
            // behavior, and everything no-storage mode already accepts) if
            // the download itself fails, so a transient fetch error never
            // costs the user the image they just generated.
            $displayUrl = $url;
            $attachment = null;
            if (!$disableStorage) {
                $attachment = $this->persistGeneratedImage($session, $prompt, $url);
                if ($attachment) {
                    $displayUrl = route('ai-chat.attachments.view', $attachment) . '?t=' . Str::random(8);
                }
            }

            $markdown = "![{$prompt}]({$displayUrl})";
            echo "data: " . json_encode(['text' => $markdown]) . "\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();

            $newTitle = null;
            if (!$disableStorage) {
                ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => '/image ' . $prompt]);
                $assistantMsg = ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $markdown]);
                echo "data: " . json_encode(['assistant_id' => $assistantMsg->id]) . "\n\n";

                if ($attachment) {
                    $attachment->update(['chat_message_id' => $assistantMsg->id]);
                }

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

    /**
     * Downloads a just-generated image from a provider's temporary URL and
     * stores it as a real ChatAttachment (same 'chat-attachments/{session}'
     * disk location and serving route as a user-uploaded image), so the
     * chat message that ends up referencing it stays valid regardless of
     * how long the provider's own hosting keeps the original around.
     * Returns null on any failure — the caller falls back to the raw URL,
     * exactly today's behavior, rather than losing the reply entirely over
     * a download hiccup.
     */
    private function persistGeneratedImage(ChatSession $session, string $prompt, string $url): ?ChatAttachment
    {
        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->successful()) {
                throw new \RuntimeException("download failed: HTTP {$response->status()}");
            }

            $mime = $response->header('Content-Type') ?: 'image/png';
            $ext  = match (true) {
                str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif')  => 'gif',
                default => 'png',
            };

            $path = "chat-attachments/{$session->id}/" . Str::uuid() . ".{$ext}";
            Storage::disk('local')->put($path, $response->body());

            return ChatAttachment::create([
                'chat_session_id' => $session->id,
                'type'            => 'image',
                'original_name'   => 'AI: ' . mb_substr($prompt, 0, 80) . ".{$ext}",
                'stored_path'     => $path,
                'mime_type'       => $mime,
                'size'            => strlen($response->body()),
                'extracted_text'  => '',
                'status'          => 'ready',
            ]);
        } catch (\Throwable $e) {
            Log::warning("laraveleasyai: could not persist generated image locally, falling back to the provider's temporary URL: {$e->getMessage()}");
            return null;
        }
    }

    private function fireWebhook(ChatSession $session, string $userMessage, string $aiReply, string $provider): void
    {
        $url = config('ai.chat.webhook.url');
        if (!$url) {
            return;
        }

        // Opt-in: queue the delivery instead of blocking the SSE stream's
        // [DONE] event on the webhook endpoint's response time. Default
        // (false) keeps today's synchronous behavior byte-identical.
        if (config('ai.chat.webhook.queue', false)) {
            SendWebhookJob::dispatch($session->id, $userMessage, $aiReply, $provider);
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
            'session_id' => 'required|integer|exists:ai_chat_sessions,id',
        ]);

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $guestToken = ChatIdentity::ensureGuestToken($guestToken);
        $ip = (string) $request->ip();

        // Bounded, not unbounded — a conversation with thousands of turns
        // would otherwise get its *entire* history fetched from the DB on
        // every single new message, just to check isEmpty()/find the last
        // assistant reply/build AI context, all of which only ever look at
        // the most recent handful of messages anyway. A single ChatSession
        // eager load isn't the N+1-prone "limit per group" case (there's
        // only one parent here), so a plain ORDER BY DESC + LIMIT works
        // correctly — re-sorted back to ascending order afterward since
        // every consumer of $session->messages below expects chronological
        // order, same as the relation's own default.
        //
        // Sorted/limited by id, not created_at: several messages created
        // within the same second (routine in fast succession, e.g. a
        // synthetic no-storage-mode append, or just quick back-and-forth)
        // share an identical created_at at second precision, and ORDER BY
        // ... DESC LIMIT N has no guaranteed tie-break order among equal
        // values — it could just as easily grab the *oldest* N of a tied
        // group as the newest. id is unique and monotonically increasing,
        // so it can't have this problem.
        $historyLoadLimit = max(1, (int) config('ai.chat.max_loaded_messages', 500));
        $session = ChatSession::with(['messages' => function ($q) use ($historyLoadLimit) {
            $q->reorder()->orderByDesc('id')->limit($historyLoadLimit);
        }])->findOrFail($request->integer('session_id'));
        $session->setRelation('messages', $session->messages->sortBy('id')->values());

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

        if (!$isRegenerate && preg_match('/^\/(?:image|img)\s+(.+)$/is', $message, $imageMatch)) {
            $imageProvider = $this->resolveImageProvider($provider);
            if ($imageProvider) {
                return $this->streamImageGeneration($session, $imageProvider, trim($imageMatch[1]), $disableStorage, $session->messages->isEmpty());
            }
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
            // A reasoning model's reply (thinking + a long answer) can
            // legitimately run past whatever max_execution_time the host's
            // php.ini enforces — that fires as an *uncatchable* fatal error
            // at whatever line happens to be executing, which is always
            // somewhere inside the AI call, never in the save step below
            // it. The result: the browser finishes rendering the full reply
            // (already streamed chunk by chunk) while the DB silently never
            // gets it, because the code that saves it never gets to run.
            // Found live: a 300s php.ini cap killed the request exactly
            // 300s after the user's message, losing an otherwise-complete
            // reply. This is a long-lived SSE stream, not a normal request
            // — it should not be subject to that limit at all.
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $fullReply = '';
            $saved     = false;
            $sessionId = $session->id;

            // Safety net for every *other* way this script can die mid-
            // stream without warning — memory_limit, a proxy/web-server
            // timeout PHP itself never sees, a fatal in a driver, etc. If
            // the browser already received content but the normal save
            // path below never ran, persist whatever was generated rather
            // than losing it silently. Captures a plain session id, not
            // the Eloquent model, and never lets a logging failure escape
            // — this can run at the very tail of the request lifecycle,
            // where making assumptions about what's still available is
            // exactly the kind of thing that got us here in the first place.
            register_shutdown_function(function () use (&$fullReply, &$saved, $sessionId, $disableStorage) {
                if ($saved || $fullReply === '' || $disableStorage) {
                    return;
                }
                try {
                    ChatMessage::create([
                        'chat_session_id' => $sessionId,
                        'role'            => 'assistant',
                        'content'         => $fullReply,
                    ]);
                } catch (\Throwable $e) {
                    try {
                        Log::error('laraveleasyai: shutdown-safety-net save failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                    } catch (\Throwable) {
                        // Nothing more we can do at this point in the lifecycle.
                    }
                }
            });

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

            // Opt-in agent-loop path (config('ai.chat.tools_enabled')) — see
            // that key's docblock in config/ai.php. $tools stays empty
            // (default config), so every existing install falls straight
            // into the untouched ->stream() branch below exactly as before
            // this existed.
            $tools = config('ai.chat.tools_enabled', false) ? $this->enabledTools() : [];

            // A failed turn used to leave $fullReply empty, so nothing got
            // saved below and the shutdown safety-net above also no-ops on
            // an empty reply — the user's message (already saved earlier)
            // was left as an orphaned, unpaired turn with no assistant
            // reply after it. Found live: the *next* message then replays
            // that broken alternation as history, which on Ollama made an
            // otherwise-sensible model (qwen3:8b) call the web_search tool
            // for a plain "hi" — it was still trying to resolve the
            // previous, never-answered question. On providers that
            // strictly enforce alternating user/assistant roles (OpenAI,
            // Anthropic) the same gap could error the next turn outright
            // instead of just confusing it. Persisting a placeholder here
            // — same "⚠ " text the client already renders live, so nothing
            // about what the user sees changes — keeps history honest for
            // whatever comes next, and means reloading the page no longer
            // makes the error vanish (today it's a client-side-only echo,
            // never saved).
            $turnFailed = false;
            try {
                if (!empty($tools)) {
                    AI::provider($provider)
                        ->model($chatModel)
                        ->systemPrompt((string) $systemPrompt)
                        ->timeout(300)
                        ->tools($tools)
                        ->run(
                            $history,
                            (int) config('ai.agent.max_steps', 5),
                            function ($call, $result) {
                                echo "data: " . json_encode(['tool_call' => [
                                    'name'      => $call->name,
                                    'arguments' => $call->arguments,
                                ]]) . "\n\n";
                                if (ob_get_level() > 0) { ob_flush(); }
                                flush();
                            },
                            function (string $chunk, string $type = 'content') use (&$fullReply) {
                                if ($type === 'thinking') {
                                    echo "data: " . json_encode(['thinking' => $chunk]) . "\n\n";
                                } else {
                                    $fullReply .= $chunk;
                                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                                }
                                if (ob_get_level() > 0) { ob_flush(); }
                                flush();
                            }
                        );
                } else {
                    AI::provider($provider)
                        ->model($chatModel)
                        ->systemPrompt((string) $systemPrompt)
                        ->timeout(300)
                        ->stream(
                            $history,
                            function (string $chunk, string $type = 'content') use (&$fullReply) {
                                if ($type === 'thinking') {
                                    echo "data: " . json_encode(['thinking' => $chunk]) . "\n\n";
                                } else {
                                    $fullReply .= $chunk;
                                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                                }
                                if (ob_get_level() > 0) { ob_flush(); }
                                flush();
                            }
                        );
                }
            } catch (ConnectionException $e) {
                $errorText   = ChatGuard::publicErrorMessage($e, $request);
                $fullReply   = '⚠ ' . $errorText;
                $turnFailed  = true;
                echo "data: " . json_encode(['error' => $errorText]) . "\n\n";
            } catch (\Exception $e) {
                $errorText   = ChatGuard::publicErrorMessage($e, $request);
                $fullReply   = '⚠ ' . $errorText;
                $turnFailed  = true;
                echo "data: " . json_encode(['error' => $errorText]) . "\n\n";
            }

            if ($fullReply && !$disableStorage) {
                try {
                    $assistantMsg = ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'role'            => 'assistant',
                        'content'         => $fullReply,
                    ]);
                    $saved = true;
                    echo "data: " . json_encode(['assistant_id' => $assistantMsg->id]) . "\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();

                    if (!$turnFailed) {
                        $this->fireWebhook($session, $message, $fullReply, $provider);
                    }
                } catch (\Throwable $e) {
                    // Let the shutdown-function safety net above have a shot
                    // at it too ($saved is still false) — this just stops a
                    // DB-level failure here (e.g. a transient connection
                    // drop) from being a raw uncaught exception.
                    Log::error('laraveleasyai: failed to save assistant reply', ['session_id' => $session->id, 'error' => $e->getMessage()]);
                }
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
