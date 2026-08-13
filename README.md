<p align="center">
  <img src="https://raw.githubusercontent.com/easybdit/laravelai/main/art/banner.svg" width="100%" alt="LaravelAI Banner">
</p>

<h1 align="center">LaravelAI</h1>

<p align="center">
  <strong>One interface, any AI.</strong><br>
  Unified AI chat for Laravel — Ollama, OpenAI (ChatGPT), Anthropic (Claude), DeepSeek
</p>

<p align="center">
  <sub>👨‍💻 Full Stack Laravel Vue Developer and DevOps Engineer</sub>
</p>

<p align="center">
  <a href="https://packagist.org/packages/easybdit/laraveleasyai"><img src="https://img.shields.io/packagist/v/easybdit/laraveleasyai.svg?style=flat-square&label=version" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/easybdit/laraveleasyai"><img src="https://img.shields.io/packagist/dt/easybdit/laraveleasyai.svg?style=flat-square&label=downloads" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/easybdit/laraveleasyai"><img src="https://img.shields.io/packagist/l/easybdit/laraveleasyai.svg?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/easybdit/laraveleasyai"><img src="https://img.shields.io/packagist/php-v/easybdit/laraveleasyai.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://github.com/easybdit/laravelai/actions"><img src="https://img.shields.io/github/actions/workflow/status/easybdit/laravelai/tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-built-in-chat-ui">Chat UI</a> •
  <a href="#-projects--knowledge-bases">Projects</a> •
  <a href="#-rag-built-in">RAG</a> •
  <a href="#-security--trust">Security</a> •
  <a href="#%EF%B8%8F-provider-settings-ui--auth-guard">Settings UI</a> •
  <a href="#-chat-ux--personalization">UX &amp; Widget</a> •
  <a href="#-providers">Providers</a> •
  <a href="#-api-reference">API Reference</a> •
  <a href="#%EF%B8%8F-configuration">Configuration</a> •
  <a href="#-troubleshooting">Troubleshooting</a>
</p>

<p align="center">
  <a href="https://www.facebook.com/easybdit">📘 Facebook Page</a> •
  <a href="https://www.facebook.com/groups/eitbd">👥 Facebook Group</a> •
  <a href="https://chat.whatsapp.com/E3VV0K6lkrqEgXdngrt2Rk">💬 WhatsApp Group</a>
</p>

---

## 📺 Video Tutorials

<table>
  <tr>
    <td align="center" width="33%">
      <a href="https://youtu.be/m_HyTIBRAOE">
        <img src="https://img.youtube.com/vi/m_HyTIBRAOE/hqdefault.jpg" width="100%" alt="Self-Hosted AI Server"><br>
        <b>🖥️ Self-Hosted AI Server</b>
      </a>
      <br><sub>Set up your own local AI server with Ollama</sub>
    </td>
    <td align="center" width="33%">
      <a href="https://youtu.be/pSwewtXqgP8">
        <img src="https://img.youtube.com/vi/pSwewtXqgP8/hqdefault.jpg" width="100%" alt="Laravel AI Package"><br>
        <b>🚀 Laravel AI Package Setup</b>
      </a>
      <br><sub>Install and use LaravelAI in your project</sub>
    </td>
    <td align="center" width="33%">
      <a href="https://youtu.be/pSwewtXqgP8">
        <img src="https://img.youtube.com/vi/pSwewtXqgP8/hqdefault.jpg" width="100%" alt="Built-in Chat UI"><br>
        <b>💬 Built-in Chat UI</b>
      </a>
      <br><sub>Zero-setup ChatGPT-like app included</sub>
    </td>
  </tr>
</table>

---

## Why LaravelAI?

Building AI features in Laravel normally means separate SDKs, different formats, and custom error handling for every provider. **LaravelAI eliminates all of that.**

```php
// Same code. Any provider. Just change the name.
$response = AI::provider('ollama')->chat($messages);    // Self-hosted, free
$response = AI::provider('openai')->chat($messages);    // ChatGPT
$response = AI::provider('anthropic')->chat($messages); // Claude
$response = AI::provider('deepseek')->chat($messages);  // DeepSeek
```

Built on **Laravel's driver pattern** — same architecture as Mail, Cache, and Queue.

---

## 📦 Installation

**Step 1:** Install via Composer

```bash
composer require easybdit/laraveleasyai
```

**Step 2:** Publish config and assets

```bash
php artisan vendor:publish --tag=ai-config
php artisan vendor:publish --tag=ai-chat-assets
```

**Step 3:** Run migrations

```bash
php artisan migrate
```

**Step 4:** Add to `.env`

```env
AI_PROVIDER=ollama
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
```

**Step 5:** Visit `/ai-chat` in your browser ✅

### Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | 8.2+    |
| Laravel     | 10, 11, 12, 13 |

---

## 🚀 Quick Start

```php
use EasyAI\LaravelAI\Facades\AI;

$response = AI::chat([['role' => 'user', 'content' => 'What is Laravel?']]);
echo $response->content;
```

### One-Liner Helper

```php
$answer = ai('What is Laravel?');
```

### Test in Tinker

```bash
php artisan tinker
>>> AI::provider('ollama')->health()
=> true
>>> ai('Say hello in 3 words')
=> "Hello there, friend!"
```

---

## 💬 Built-in Chat UI

> **New in v1.3.0** — A full ChatGPT-like chat app included. Zero setup required.

### What you get out of the box

| Feature | Description |
|---------|-------------|
| 💬 Chat UI | ChatGPT-like sidebar with session history |
| ⚡ Streaming | Real-time typing effect |
| 📝 Markdown | Full rendering with syntax-highlighted code |
| 📋 Copy buttons | Per message and per code block |
| 🔄 Provider switcher | Switch Ollama / OpenAI / Claude / DeepSeek live |
| 💾 DB persistence | History survives page refresh |
| 🏷️ Auto-title | First message becomes session title |
| 📁 Projects | RAG-powered knowledge bases (v1.4.0) |
| 📦 Offline assets | No CDN dependency |
| 🔒 Security suite | Rate limiting, access control, captcha, GDPR gate (v2.0.0) |
| 📎 Attachments | Vision + document uploads mid-chat (v2.0.0) |
| 🧩 Floating widget | `<x-laravelai::widget />` — embeddable anywhere (v2.0.0) |
| 📊 Analytics | Usage dashboard, zero external tracking (v2.0.0) |
| 📱 Responsive | Off-canvas sidebar drawer, full-screen modals, and touch-sized controls below 768px |
| 🧠 Live thinking | Collapsible "Thinking…" block for reasoning models (Ollama qwen3, Anthropic extended thinking, Gemini 2.5) instead of dead air |

### Customize the view

```bash
php artisan vendor:publish --tag=ai-chat-views
# → resources/views/vendor/laravelai/chat.blade.php
```

### Routes registered automatically

| Method | URL | Description |
|--------|-----|-------------|
| GET    | `/ai-chat` | Chat UI |
| GET    | `/ai-chat/analytics` | Usage dashboard |
| POST   | `/ai-chat/api/sessions` | Create session |
| DELETE | `/ai-chat/api/sessions/{id}` | Delete session |
| POST   | `/ai-chat/api/stream` | SSE streaming (POST — see note below) |
| POST   | `/ai-chat/api/provider` | Switch provider |
| GET    | `/ai-chat/api/captcha` | Fetch a math captcha challenge |
| POST   | `/ai-chat/api/messages/{id}/feedback` | 👍/👎 a message |
| POST   | `/ai-chat/api/attachments` | Upload a chat attachment (image/document) |
| GET    | `/ai-chat/api/attachments/{id}` | Stream an attachment back |
| DELETE | `/ai-chat/api/attachments/{id}` | Delete an attachment |
| GET    | `/ai-chat/api/projects` | List projects |
| POST   | `/ai-chat/api/projects` | Create project |
| DELETE | `/ai-chat/api/projects/{id}` | Delete project |
| GET    | `/ai-chat/api/projects/{id}/files` | List project files |
| POST   | `/ai-chat/api/projects/{id}/files` | Upload & ingest file |
| POST   | `/ai-chat/api/projects/{id}/files/{fid}/reprocess` | Re-chunk a file in place |
| DELETE | `/ai-chat/api/projects/{id}/files/{fid}` | Delete file |
| POST   | `/ai-chat/api/rag/test` | Inspect matched chunks for a query |

> `/ai-chat/api/stream` moved from GET to POST in v2.0.0 — a 4000-character message no longer risks the URL length limit, and the built-in view reads the response via `fetch()` + a manual SSE parser (not `EventSource`) so blocked/rate-limited JSON error responses are actually readable.

> **v2.1.1** — `/ai-chat/api/stream` now survives replies that run long (a reasoning model thinking + writing a full answer can easily take a few minutes). Before this fix, a slow reply could hit your server's `max_execution_time` and vanish *after* the user had already watched it finish streaming — nothing showed as an error, it just wasn't there on the next page load. See [Troubleshooting → A reply appears while streaming, then disappears after reload](#a-reply-appears-while-streaming-then-disappears-after-reload) for the real example and the fix.

---

## 🗂️ Projects & Knowledge Bases

> **New in v1.4.0** — Self-hosted Claude-like Projects. Create knowledge bases, upload documents, and get RAG-powered answers scoped per project.

### How it works

```
Create Project → Upload Files → Chat Inside Project → RAG answers from your docs
```

1. Click **＋** next to **Projects** in the sidebar
2. Upload `.txt`, `.md`, or `.pdf` files — auto-ingested into RAG on upload
3. Click the project to start a new RAG-powered chat session
4. Every message retrieves relevant context from **that project's documents only**
5. Normal chats outside projects are completely unaffected

### What you see in the UI

- 📁 **Projects section** in sidebar with file count badge
- 🧠 **RAG ON** badge in chat header when inside a project session
- 📎 **Manage Files** button — upload, view ingestion status, delete files
- 🟢 Status per file: `pending` → `ingested` → `failed`
- **Project context active** indicator in the input footer

### PDF support (optional)

```bash
composer require smalot/pdfparser
```

### RAG Scoping API

```php
$results = AI::rag()->source('project_5')->search('your query');
$answer  = AI::rag()->source('project_5')->ask('your question');
AI::rag()->flush('project_5');
```

---

## 🧠 RAG (Built-in)

No external vector database required — uses your existing SQL database.

### Setup

```bash
ollama pull nomic-embed-text
php artisan migrate
```

```env
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
```

### Usage

```php
// Store
AI::rag()->ingest('Laravel is a PHP framework using MVC.', 'docs');

// Ask
$answer = AI::rag()->ask('What is Laravel?');

// Search
$results = AI::rag()->search('MVC pattern');
// [['content' => '...', 'source' => 'docs', 'score' => 0.91]]

// Scoped
$results = AI::rag()->source('project_5')->search('your query');

// Flush
AI::rag()->flush();
AI::rag()->flush('project_5');
```

### Artisan

```bash
php artisan ai:rag:ingest storage/docs/manual.txt --source=manual
php artisan ai:rag:ingest storage/docs/ --flush
```

### Accurate counting, test queries, reprocessing

> **New in v2.0.0**

```php
// Full-corpus keyword scan for "how many X" questions — top-K semantic
// search alone under-counts when relevant chunks rank outside K.
AI::rag()->countMatches('invoice', 'project_5');
```

```
POST /ai-chat/api/rag/test          { "query": "refund policy", "source": "project_5" }
POST /ai-chat/api/projects/5/files/12/reprocess   # re-chunk in place, no re-upload
```

### Auto-index your own models ("Ask This Site")

Keep RAG in sync with any Eloquent model on save/delete — opt-in, empty by default:

```php
// config/ai.php
'rag' => [
    'auto_index' => [
        'posts' => [
            'model'  => \App\Models\Post::class,
            'source' => 'site_posts',
            'text'   => fn ($post) => $post->title . "\n\n" . strip_tags($post->body),
            'when'   => fn ($post) => $post->is_published, // optional
        ],
    ],
],
```

Chat sessions with no project attached automatically pull context from every auto-indexed source once at least one entry is configured.

### RAG Configuration

| `.env` Key | Default | Description |
|------------|---------|-------------|
| `AI_RAG_PROVIDER` | `ollama` | Embedding provider |
| `AI_RAG_EMBED_MODEL` | `nomic-embed-text` | Embedding model |
| `AI_RAG_CHUNK_SIZE` | `2000` | Max chars per chunk |
| `AI_RAG_TOP_K` | `3` | Chunks retrieved per query |
| `AI_RAG_TABLE` | `ai_documents` | Database table |

---

## 🔒 Security & Trust

> **New in v2.0.0** — everything below is config/env-driven (`config/ai.php` → `chat`); there's no settings database or admin panel, by design — your app already has its own way to manage config.

| Setting | `.env` key | Default |
|---|---|---|
| Rate limit (per identity) | `AI_CHAT_RATE_LIMIT_MAX` / `_WINDOW` | 20 / 60s |
| Rate limit (per IP, hard cap) | `AI_CHAT_RATE_LIMIT_IP_MAX` | 60 |
| Access restriction | `AI_CHAT_ACCESS_RESTRICTION` | `everyone` (also: `auth`, `role`, `gate`) |
| IP blocklist | `AI_CHAT_IP_BLOCKLIST` | — (comma-separated) |
| Word filter | `AI_CHAT_WORD_FILTER_ENABLED` / `_WORDS` | off |
| Prompt-injection detection | `AI_CHAT_PROMPT_INJECTION_DETECT` | off |
| No-storage mode | `AI_CHAT_DISABLE_STORAGE` | off |
| Math captcha (first message) | `AI_CHAT_CAPTCHA_ENABLED` | off |
| Abuse-alert email | `AI_CHAT_ABUSE_ALERT_ENABLED` / `_EMAIL` | off |
| GDPR consent gate | `AI_CHAT_GDPR_GATE_ENABLED` | off |
| Lock system prompt | `AI_CHAT_LOCK_SYSTEM_PROMPT` | off |
| Max message length | `AI_CHAT_MAX_MESSAGE_LENGTH` | 4000 |

"Role" restriction looks for `$user->hasAnyRole([...])` (configurable method name — works with Spatie permission out of the box) or a `roles` collection/array on the user model. "Gate" restriction checks a named Laravel `Gate` you define yourself (`AI_CHAT_ACCESS_GATE`, defaults to `use-ai-chat`).

Internal exception detail (e.g. "No API key configured for OpenAI") is only shown to callers who pass the same gate — everyone else gets a generic "temporarily unavailable" message. Set `AI_CHAT_SHOW_INTERNAL_ERRORS=true` to always show real errors (e.g. in a staging environment).

## ⚙️ Provider Settings UI & Auth Guard

Change providers/API keys from `/ai-chat/settings` instead of editing `.env` and redeploying. `.env` stays the source of truth for everything you never touch in the UI — this is a purely additive override layer, not a replacement.

```php
// AppServiceProvider (or any provider) — required, fail-closed by default:
Gate::define('manage-ai-settings', fn ($user) => $user->isAdmin());
```

Without that Gate defined, **every** request to the Settings page is refused — there's no "everyone" mode for editing API keys, regardless of how the main chat's own access restriction is configured. Secrets are masked in the UI (last 4 characters only) and never overwritten unless you actually type a new value; blanking a field deletes the override and falls back to `.env` again.

**Storage:** API keys are encrypted (Laravel's `Crypt`, your app's `APP_KEY`) before ever touching the database — never stored in plaintext. Every provider driver's `health()` (used by the page's "Test connection" button) is also audited to confirm none of them can leak a credential through an exception message.

**Auth-gating the chat itself** is a separate, independent knob — public by default:

```env
AI_CHAT_MIDDLEWARE=auth   # require login for the whole /ai-chat area; leave unset for a public page
```

## 💬 Chat UX & Personalization

> **New in v2.0.0**

Stop & regenerate, message timestamps, per-message and whole-conversation export, read-aloud (browser `SpeechSynthesis`), voice input (browser `SpeechRecognition` — Chrome/Edge/Safari), fullscreen mode, 👍/👎 feedback, and client-side session search all ship in the default `resources/views/chat.blade.php` — no configuration needed, some gated by `AI_CHAT_VOICE_INPUT_ENABLED` / `AI_CHAT_TTS_ENABLED` / `AI_CHAT_EXPORT_ENABLED`.

Welcome message, suggested-question chips, a custom AI avatar, and accent/bubble colors are all `config('ai.chat.ui')` / env-driven:

```env
AI_CHAT_WELCOME_ENABLED=true
AI_CHAT_WELCOME_MESSAGE="Hello! How can I help you today?"
AI_CHAT_SUGGESTED_QUESTIONS="What can you help with?|Summarize a document|Write me an email"
AI_CHAT_AVATAR_URL=https://example.com/bot-avatar.png
AI_CHAT_COLOR_ACCENT=#6366f1
```

### Bot profiles

Named presets — provider, title, system prompt — loadable per session:

```php
// config/ai.php
'bot_profiles' => [
    'support' => [
        'label'         => 'Support Bot',
        'provider'      => 'openai',
        'system_prompt' => 'You are a friendly support agent for Acme Inc.',
    ],
],
```

```php
POST /ai-chat/api/sessions  { "profile": "support" }
```

### Floating widget (embeddable anywhere)

A self-contained launcher + chat panel — talks to the same session/stream API, ships its own scoped CSS and JS, and works on any Blade view without loading the full `/ai-chat` app:

```blade
<x-laravelai::widget position="bottom-right" label="Chat with us" profile="support" />
```

### Export as PDF, Word, Excel, or PowerPoint

The export button next to the chat header is a dropdown — plain text works with zero setup (client-side), the other four are optional server-side dependencies, install only the ones you want:

```bash
composer require dompdf/dompdf              # PDF
composer require phpoffice/phpword          # Word (.docx)
composer require phpoffice/phpspreadsheet   # Excel (.xlsx) — one row per message
composer require phpoffice/phppresentation  # PowerPoint (.pptx) — one slide per message
```

Not installed yet? The download attempt shows exactly which command to run instead of failing silently. PowerPoint is the odd one out of the four — slides don't paginate long text the way a document does, so it suits short conversations best; PDF or Word read better for a long transcript.

## 📎 Attachments & Vision

> **New in v2.0.0** — `AI_CHAT_ATTACHMENTS_ENABLED=true`

Upload images and documents (`.txt`/`.md`/`.pdf`) mid-chat. Documents are text-extracted and appended as context for **every** provider; images become real vision input for OpenAI, Anthropic, and Gemini (via a universal multipart message format translated per-provider), with a "this provider can't view images" fallback note for everyone else.

## 📊 Analytics & Webhooks

Visit `/ai-chat/analytics` for a zero-external-tracking dashboard — total conversations/messages, messages today, active chats (7d), most-used provider, a 7-day bar chart, and feedback stats — computed entirely from your own `chat_sessions`/`chat_messages` tables.

```env
AI_CHAT_WEBHOOK_URL=https://your-endpoint.example.com/hook
AI_CHAT_WEBHOOK_SECRET=optional-hmac-secret
```

Fires a best-effort POST after every AI response with `session_id`, `user_message`, `ai_response`, `provider`, `timestamp` — and an `X-LaravelAI-Signature: sha256=...` header when a secret is set. Compatible with Zapier, Make, n8n, or any HTTP endpoint.

## 🤖 Providers

### Ollama — Self-Hosted & Free

```env
AI_PROVIDER=ollama
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
AI_OLLAMA_TIMEOUT=120
```

> **Note for small models (qwen2, qwen2.5):** If you get 400 errors with RAG context, set `num_ctx` to match your model's context window:
> ```bash
> ollama show qwen2:1.5b --modelfile > /tmp/modelfile
> echo "PARAMETER num_ctx 2048" >> /tmp/modelfile
> ollama create qwen2-fixed -f /tmp/modelfile
> ```
> Then use `AI_OLLAMA_MODEL=qwen2-fixed` in `.env`.

**Reasoning models (qwen3 and similar) "think" before answering** — often adding 10–30+ seconds of latency for a simple question, with *nothing* visible while it happens. The built-in chat UI shows this live as a collapsible "🧠 Thinking… Ns" block instead of dead air, auto-collapsing to "Thought for Ns" once the real answer starts — same idea as Claude.ai's/ChatGPT's extended-thinking UI. Supported on Ollama, Anthropic, and Gemini (see their sections below); OpenAI's o-series reasoning models don't expose visible reasoning through the Chat Completions API this package uses, so there's nothing to show for OpenAI. If you'd rather skip the wait entirely on Ollama:

```env
AI_OLLAMA_THINK=false
```

```php
// or per-call
AI::provider('ollama')->think(false)->chat($messages);
```

### OpenAI (ChatGPT)

```env
AI_OPENAI_KEY=sk-your-api-key
AI_OPENAI_MODEL=gpt-4o-mini
```

### Anthropic (Claude)

```env
AI_ANTHROPIC_KEY=sk-ant-your-api-key
AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

Extended thinking is off by default (it costs extra tokens and time). Turn it on and the chat UI shows the same live "🧠 Thinking…" block as Ollama:

```env
AI_ANTHROPIC_THINK=true
AI_ANTHROPIC_THINK_BUDGET=10000
```

### DeepSeek

```env
AI_DEEPSEEK_KEY=sk-your-api-key
AI_DEEPSEEK_MODEL=deepseek-chat
```

### Google Gemini

```env
AI_GEMINI_KEY=your-api-key
AI_GEMINI_MODEL=gemini-2.0-flash
```

2.5-series models support the same live thinking display:

```env
AI_GEMINI_THINK=true
```

### Together AI

```env
AI_TOGETHER_KEY=your-api-key
AI_TOGETHER_MODEL=meta-llama/Llama-3.3-70B-Instruct-Turbo

# Optional: FLUX image generation via "/image a red fox in snow" in chat
AI_TOGETHER_IMAGE_ENABLED=true
```

### Custom (any OpenAI-compatible endpoint)

LM Studio, vLLM, OpenRouter, an in-house gateway — add as many named entries as you like in `config/ai.php`:

```php
'custom_providers' => [
    'lmstudio' => [
        'label'   => 'LM Studio (local)',
        'url'     => 'http://127.0.0.1:1234/v1',
        'api_key' => null,
        'model'   => 'local-model',
        'timeout' => 60,
    ],
],
```

```php
AI::custom('lmstudio')->chat($messages);
// or, in the chat UI provider selector: "custom:lmstudio"
```

---

## ✨ Features

### Fluent Builder API

```php
$response = AI::provider('ollama')
    ->model('qwen2:1.5b')
    ->temperature(0.9)
    ->maxTokens(500)
    ->systemPrompt('You are a helpful Laravel expert.')
    ->chat([['role' => 'user', 'content' => 'Explain middleware']]);
```

### Streaming

```php
AI::provider('ollama')->stream(
    [['role' => 'user', 'content' => 'Write a poem']],
    function (string $chunk) { echo $chunk; }
);
```

### Health Check + Fallback

```php
foreach (['ollama', 'deepseek', 'openai'] as $provider) {
    try {
        if (!AI::provider($provider)->health()) continue;
        return AI::provider($provider)->chat($messages)->content;
    } catch (\Throwable $e) {
        Log::warning("{$provider} failed: {$e->getMessage()}");
    }
}
```

### Token Estimation

```php
$tokens = AI::estimateTokens('Hello world');
$tokens = AI::estimateTokens($messagesArray);
```

### Ollama Advanced Features

```php
AI::provider('ollama')->format('json')->chat($messages);
AI::provider('ollama')->embed('Hello world');
AI::provider('ollama')->keepAlive('10m')->chat($messages);
AI::provider('ollama')->options(['num_ctx' => 2048])->chat($messages);
AI::provider('ollama')->pullModel('llama3.1:8b');
AI::provider('ollama')->runningModels();
AI::provider('ollama')->deleteModel('old-model');
```

### Error Handling

```php
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;

try {
    $response = AI::provider('openai')->chat($messages);
} catch (ConnectionException $e) {
    Log::error("Connection failed: " . $e->getMessage());
} catch (ProviderException $e) {
    Log::error("Provider [{$e->getProvider()}]: " . $e->getMessage());
}
```

---

## 📖 API Reference

### Facade Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `AI::chat(array $messages)` | `AIResponse` | Chat with default provider |
| `AI::provider(string $name)` | `AIProvider` | Switch provider — accepts `"custom:slug"` too |
| `AI::custom(string $key)` | `CustomDriver` | Resolve a named custom OpenAI-compatible provider |
| `AI::estimateTokens(string\|array)` | `int` | Estimate token count |
| `AI::rag()` | `RAGManager` | Access RAG system |

### Provider Methods (Chainable)

| Method | Description |
|--------|-------------|
| `->model($name)` | Set the model |
| `->temperature($float)` | Creativity (0–2) |
| `->maxTokens($int)` | Max response tokens |
| `->systemPrompt($text)` | Set instructions |
| `->timeout($seconds)` | Request timeout |
| `->chat(array $messages)` | Send and get response |
| `->stream(array $messages, callable)` | Stream token by token |
| `->health()` | Check provider reachable |
| `->models()` | List available models |

### RAG Methods

| Method | Description |
|--------|-------------|
| `->ingest($text, $source)` | Store as embeddings |
| `->search($query)` | Similarity search |
| `->ask($question)` | RAG-powered Q&A |
| `->source($name)` | Scope to one source |
| `->flush($source?)` | Delete documents |
| `->countMatches($term, $source?)` | Full-corpus keyword count for "how many X" questions |
| `->searchAutoIndexed($query)` | Search everything AutoIndexer has indexed |

### Ollama-Only Methods

| Method | Description |
|--------|-------------|
| `->format('json')` | Force JSON output |
| `->embed($text)` | Generate embedding |
| `->keepAlive($duration)` | Keep in memory |
| `->options($array)` | Raw Ollama options (e.g. `num_ctx`) |
| `->think($bool)` | Toggle reasoning mode on models that support it (qwen3, etc.) |
| `->pullModel($name)` | Download model |
| `->showModel($name)` | Model details |
| `->deleteModel($name)` | Remove model |
| `->copyModel($src, $dst)` | Copy model |
| `->runningModels()` | List loaded models |

### AIResponse Object

| Property | Type | Description |
|----------|------|-------------|
| `$response->content` | `string` | AI reply text |
| `$response->model` | `string` | Model used |
| `$response->promptTokens` | `int` | Input tokens |
| `$response->replyTokens` | `int` | Output tokens |
| `$response->totalTokens` | `int` | Total tokens |
| `$response->provider` | `string` | Provider name |
| `$response->getRaw()` | `array` | Raw API response |
| `(string) $response` | `string` | Cast to string |

### Helper Function

```php
ai('Your question')
ai('Your question', 'openai')
ai('Your question', 'anthropic', 'claude-haiku-...')
```

---

## ⚙️ Configuration

```php
// config/ai.php
return [
    'default' => env('AI_PROVIDER', 'ollama'),
    'providers' => [
        'ollama'    => ['driver' => 'ollama',    'url'     => env('AI_OLLAMA_URL'),    'model' => env('AI_OLLAMA_MODEL', 'qwen2:1.5b'),      'timeout' => env('AI_OLLAMA_TIMEOUT', 120)],
        'openai'    => ['driver' => 'openai',    'api_key' => env('AI_OPENAI_KEY'),    'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),      'timeout' => 60],
        'anthropic' => ['driver' => 'anthropic', 'api_key' => env('AI_ANTHROPIC_KEY'), 'model' => env('AI_ANTHROPIC_MODEL'),                  'timeout' => 60],
        'deepseek'  => ['driver' => 'deepseek',  'api_key' => env('AI_DEEPSEEK_KEY'),  'model' => env('AI_DEEPSEEK_MODEL', 'deepseek-chat'),  'timeout' => 60],
    ],
    'rag' => [
        'embed_provider' => env('AI_RAG_PROVIDER', 'ollama'),
        'embed_model'    => env('AI_RAG_EMBED_MODEL', 'nomic-embed-text'),
        'chat_provider'  => env('AI_RAG_CHAT_PROVIDER', null),
        'chunk_size'     => (int) env('AI_RAG_CHUNK_SIZE', 2000),
        'top_k'          => (int) env('AI_RAG_TOP_K', 3),
        'table'          => env('AI_RAG_TABLE', 'ai_documents'),
        'system_prompt'  => env('AI_RAG_SYSTEM_PROMPT', 'Answer using ONLY the context below. If unsure, say so.'),
    ],
];
```

### Complete `.env` Reference

```env
# Provider
AI_PROVIDER=ollama

# Ollama (self-hosted, free)
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
AI_OLLAMA_TIMEOUT=120
# AI_OLLAMA_THINK=false   # skip reasoning-model "thinking" latency (qwen3, etc.)

# OpenAI
AI_OPENAI_KEY=sk-proj-xxxx
AI_OPENAI_MODEL=gpt-4o-mini

# Anthropic (Claude)
AI_ANTHROPIC_KEY=sk-ant-xxxx
AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514

# DeepSeek
AI_DEEPSEEK_KEY=sk-xxxx
AI_DEEPSEEK_MODEL=deepseek-chat

# Gemini
AI_GEMINI_KEY=your-api-key
AI_GEMINI_MODEL=gemini-2.0-flash

# Together AI
AI_TOGETHER_KEY=your-api-key
AI_TOGETHER_MODEL=meta-llama/Llama-3.3-70B-Instruct-Turbo
AI_TOGETHER_IMAGE_ENABLED=false

# RAG
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHUNK_SIZE=500
AI_RAG_TOP_K=1
AI_RAG_TABLE=ai_documents

# RAG for small models — reduce chunk size and limit context
# AI_OLLAMA_NUM_CTX=2048

# Security & Trust (v2.0.0) — see the Security & Trust section above for the full list
AI_CHAT_RATE_LIMIT_ENABLED=true
AI_CHAT_RATE_LIMIT_MAX=20
AI_CHAT_RATE_LIMIT_WINDOW=60
AI_CHAT_ACCESS_RESTRICTION=everyone
AI_CHAT_DISABLE_STORAGE=false
AI_CHAT_CAPTCHA_ENABLED=false
AI_CHAT_GDPR_GATE_ENABLED=false
AI_CHAT_MAX_MESSAGE_LENGTH=4000

# Chat UX & personalization (v2.0.0)
AI_CHAT_WELCOME_ENABLED=false
AI_CHAT_VOICE_INPUT_ENABLED=true
AI_CHAT_TTS_ENABLED=true
AI_CHAT_EXPORT_ENABLED=true
AI_CHAT_ATTACHMENTS_ENABLED=false

# Webhook (v2.0.0)
AI_CHAT_WEBHOOK_URL=
AI_CHAT_WEBHOOK_SECRET=
```

---

## 🧪 Testing

```bash
vendor/bin/phpunit
vendor/bin/phpunit --filter=test_ollama_chat
```

Uses `Http::fake()` — no real API calls needed.

---

## 🩹 Troubleshooting

Real problems, found and fixed on real installs — kept here with the actual example so the fix makes sense.

### A reply appears while streaming, then disappears after reload

**Symptom:** you ask a question, watch the AI's answer type itself out fully in the chat window — then reload the page (or come back later) and the reply is gone. Only your message is still there.

**Real example this was found from** — a chat asking a local Ollama reasoning model (`qwen3:8b`) to explain "how to setup laravel 12 on windows xampp". The full answer streamed to the screen normally. The server's log told the real story:

```
[2026-08-13 23:42:01] local.ERROR: Maximum execution time of 300 seconds exceeded
```

...timestamped exactly 300 seconds after the question was sent. A reasoning model's reply (thinking + a long, detailed answer) can genuinely take that long, and PHP's `max_execution_time` (300s is XAMPP's typical default) doesn't care that the browser is happily still receiving data — once the clock runs out, PHP kills the script with an error that **cannot be caught in code**, wherever it happens to be running. That's always somewhere inside the AI request, never in the step right after it that saves the reply to your database. The user sees a complete answer; the database never gets it.

**Fixed in v2.1.1** — `/ai-chat/api/stream` now lifts PHP's execution-time limit for itself (`set_time_limit(0)`) since it's a long-lived streaming response by design, not a normal page request, plus a backup save that still catches the reply even if the process dies for some *unrelated* reason. Nothing to configure — this is automatic as of v2.1.1.

**One thing still outside the package's control:** if you're behind Nginx or Apache as a reverse proxy (not just XAMPP/`php artisan serve` directly), *the proxy itself* can also time out a slow request before PHP does, since removing PHP's own limit doesn't touch the web server's. If replies still cut off after upgrading, raise the proxy's read timeout too:

```nginx
# nginx — inside your site's location block
fastcgi_read_timeout 600s;
proxy_read_timeout   600s;
```

```apache
# Apache — httpd.conf or a vhost config
ProxyTimeout 600
```

### `/ai-chat` routes return HTML instead of JSON, or the chat window never loads

Usually means a catch-all route in *your own* `routes/web.php` is registered before the package's routes and swallowing everything, `/ai-chat/*` included:

```php
// ❌ This shadows every package route, including its JSON APIs
Route::get('/{any}', fn () => view('app'))->where('any', '.*');

// ✅ Use fallback() instead — it only matches when nothing else did
Route::fallback(fn () => view('app'));
```

This is common in SPA setups (Vue/React front ends living inside a Laravel app) where a wildcard route serves the SPA shell for client-side routing.

---

## 🗺️ Roadmap

| Version | Feature | Status |
|---------|---------|--------|
| v1.0 | Ollama, OpenAI, Anthropic, DeepSeek | ✅ Released |
| v1.1 | Laravel 12 & 13 support | ✅ Released |
| v1.2 | Built-in RAG system + Ollama advanced | ✅ Released |
| v1.3 | Built-in Chat UI | ✅ Released |
| v1.4 | Projects + RAG scoping (self-hosted Claude Projects) | ✅ Released |
| v2.0 | Security & Trust Suite (rate limiting, access control, captcha, GDPR gate...) | ✅ Released |
| v2.0 | Vision / Image input (OpenAI, Anthropic, Gemini) | ✅ Released |
| v2.0 | Google Gemini + Together AI + custom OpenAI-compatible drivers | ✅ Released |
| v2.0 | Chat attachments (images + documents) | ✅ Released |
| v2.0 | Analytics dashboard + webhooks | ✅ Released |
| v2.0 | Bot profiles + embeddable floating widget | ✅ Released |
| v2.0 | Image generation (Together AI / FLUX, via `/image`) | ✅ Released |
| v2.1 | Live "thinking" visibility for reasoning models (Ollama, Anthropic, Gemini) | ✅ Released |
| v2.1 | Provider Settings UI + auth guard for the chat area | ✅ Released |
| v2.1 | Claude/ChatGPT-style message layout + responsive mobile UI | ✅ Released |
| v2.1 | Multi-format export — PDF, Word, Excel, PowerPoint | ✅ Released |
| v2.1 | Settings encryption at rest for provider API keys | ✅ Released |
| v2.1.1 | Fix — assistant replies no longer lost on long-running streams | ✅ Released |
| v2.1 | Function / Tool calling | 🔜 Planned |
| v2.1 | Groq driver | 🔜 Planned |
| v2.2 | Response caching | 🔜 Planned |
| v2.2 | Commerce bot kit (order/product Q&A against a host e-commerce schema) | 🔜 Planned |

---

## ❤️ Support

<p align="center">
  <a href="https://easyit.com.bd/donate">
    <img src="https://img.shields.io/badge/Donate-Support%20This%20Project-blue?style=for-the-badge&logo=heart&logoColor=white" alt="Donate">
  </a>
  &nbsp;
  <a href="https://github.com/sponsors/muradbdinfo">
    <img src="https://img.shields.io/badge/GitHub%20Sponsors-EA4AAA?style=for-the-badge&logo=github-sponsors&logoColor=white" alt="GitHub Sponsors">
  </a>
</p>

- ⭐ **Star** this repo on GitHub
- 🐛 **Report bugs** via [Issues](https://github.com/easybdit/laravelai/issues)
- 🔀 **Submit a PR** — contributions welcome
- 📢 **Share** with your developer friends

---

## 👤 Credits

**Md Murad Hosen** — Full-Stack Laravel Vue Developer and DevOps Engineer from Chittagong, Bangladesh 🇧🇩

<table>
  <tr>
    <td>🌐 Website</td><td><a href="https://www.easyit.com.bd">easyit.com.bd</a></td>
    <td>📺 YouTube</td><td><a href="https://youtube.com/@easybdit">EasyBD IT</a></td>
  </tr>
  <tr>
    <td>📘 Facebook</td><td><a href="https://facebook.com/muradhosenofficial">Murad Hosen</a></td>
    <td>📱 WhatsApp</td><td><a href="https://wa.me/8801827517700">+8801827517700</a></td>
  </tr>
  <tr>
    <td>💻 GitHub</td><td><a href="https://github.com/muradbdinfo">muradbdinfo</a></td>
    <td>👥 FB Group</td><td><a href="https://www.facebook.com/groups/eitbd">EITBD</a></td>
  </tr>
</table>

---

## 📄 License

MIT License — free to use in personal and commercial projects. See [LICENSE](LICENSE) for details.

<p align="center">
  <sub>Made with ❤️ in Bangladesh 🇧🇩 · Built for the Laravel community worldwide</sub>
</p>
