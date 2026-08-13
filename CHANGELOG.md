# Changelog

## v2.1.1 — 2026-08-13

### 🐛 Fixed: assistant replies silently lost on long-running streams

Found live: a reasoning model's reply (thinking + a long answer) can run past whatever `max_execution_time` the host's `php.ini` enforces. That fires as an **uncatchable** PHP fatal at whatever line happens to be executing — which is always somewhere inside the AI call, never in the save step that comes after it. The user watches the full reply stream to their screen, then the process dies silently: nothing gets written to the database, and the reply is gone forever on the next page reload. Confirmed against a real deployment — a request died with `Maximum execution time of 300 seconds exceeded` exactly 300 seconds after the user's message, and the assistant's already-fully-rendered reply was never in the `chat_messages` table.

- `/ai-chat/api/stream` and the `/image` generation stream now call `set_time_limit(0)` before starting — this is a long-lived SSE response by design, not a normal request, and shouldn't be capped by a general-purpose php.ini setting at all.
- Added a `register_shutdown_function` safety net that persists whatever content was generated if the script still dies unexpectedly for any *other* reason (memory limit, a proxy/web-server timeout PHP never even sees, etc.) — this closes the whole class of bug, not just the one specific trigger that was found.
- The assistant-message save is now itself wrapped in a try/catch, so a transient DB error there can no longer become an uncaught exception that kills the response mid-stream.

New regression test covers the catchable half of this (a DB-level failure on the save); the uncatchable PHP-fatal half was confirmed by reproducing it against a live server and re-running the same request after the fix.

---

## v2.1.0 — 2026-08-13

### 📤 Multi-format conversation export (PDF, Word, Excel, PowerPoint)

Beyond the existing client-side `.txt` export, a conversation can now be downloaded server-side as PDF, Word (`.docx`), Excel (`.xlsx`), or PowerPoint (`.pptx`) from a new dropdown next to the existing export button. Each format is its own optional dependency, gracefully degrading with a clear `composer require ...` message rather than a fatal error when not installed — same pattern as `smalot/pdfparser` for RAG's PDF ingestion:

- **PDF** — `dompdf/dompdf`. Full markdown rendering (headings, lists, code blocks, bold/italic) via a new small `PdfMarkdown` converter — deliberately not a full CommonMark implementation, just enough of what AI replies actually produce, so this doesn't pull in a whole markdown package on top of dompdf.
- **Word** — `phpoffice/phpword`. Reuses the same `PdfMarkdown` output through PhpWord's own HTML importer, so message formatting isn't duplicated per format.
- **Excel** — `phpoffice/phpspreadsheet`. One row per message (#, role, timestamp, content) — the natural shape for filtering/searching a long conversation rather than a document layout.
- **PowerPoint** — `phpoffice/phppresentation`. One slide per message. Documented honestly as the odd fit of the four: slides don't paginate long text the way a document does, so this suits short-to-medium conversations best — PDF/Word read far better for a long transcript.

All four routes are ownership-checked exactly like every other session-scoped endpoint. 6 new tests verify each format actually produces valid output (real PDF/zip magic bytes), not just "didn't crash."

### 🔒 Settings encryption at rest

Provider API keys saved through the new Settings UI are now encrypted before being written to the database (Laravel's `Crypt`/`APP_KEY`), not stored in plaintext — the masked-in-the-UI guarantee was already there, this closes the matching gap at the storage layer. Corrupted/undecryptable rows (e.g. after an `APP_KEY` rotation) are skipped rather than crashing boot or leaking ciphertext into `config()`. Also audited every provider driver's `health()` method to confirm none of them can leak a credential through an exception message (all fail safe internally already) — the Settings page's test-connection endpoint now returns a fixed generic message on any resolution error as well, for defense in depth.

### 💬 Claude/ChatGPT-style message layout

User turns are now a right-aligned bubble with no avatar; assistant turns stay left-aligned, full-width, with the avatar — matching the familiar Claude.ai/ChatGPT layout instead of the previous symmetric two-column chat-log look. Pure CSS on the existing markup (`flex-direction: row-reverse` for `.msg-row.user`), so both server-rendered history and the JS-appended live message use the same rules with no HTML changes.

### ⚙️ Provider settings from the UI + auth guard

Providers/API keys could previously only be changed by editing `.env`. New opt-in `/ai-chat/settings` admin page — change the default provider, every provider's API key/model/timeout, and test each connection live, without redeploying.

- **`ai_settings` table** (new migration) is a generic key → value override store applied on top of `config()`/`.env` at boot (`SettingsOverlay`) — .env stays the source of truth for anyone who never opens the page; zero rows means zero behavior change.
- **Fail-closed by design**, independent of the main chat's own access settings: refused unless the host app defines `Gate::define('manage-ai-settings', ...)` — there is no "everyone" mode for editing API keys, same pattern as the Commerce Store Assistant.
- Secrets are **masked** in the UI (last 4 characters only) and never overwritten unless you actually type a new value; blanking a field deletes the override and falls back to `.env` again.
- **`AI_CHAT_MIDDLEWARE`** — new config for the *main chat area* separately: empty (public page) by default, set to `auth` to require login for the whole `/ai-chat` app instead of only gating individual actions.

7 new tests.

### 📱 Responsive chat UI

The built-in chat UI was desktop-only — a fixed 260px sidebar with no mobile breakpoints, genuinely broken on a phone. Now:

- Sidebar becomes an off-canvas drawer below 768px (hamburger toggle + backdrop, `Escape` or backdrop-tap to close) instead of squeezing/overflowing the page.
- Modals go full-screen on small viewports instead of a cramped fixed-width box.
- Touch-sized tap targets (inputs/send/icon buttons grow to a comfortable size) below 768px; message padding and header layout adapt down to phone widths.

### 🧠 Multi-provider "thinking" visibility

Extends Ollama's live thinking display (see below) to the other two providers with real reasoning APIs:

- **Anthropic** — extended thinking via `thinking: {type: "enabled", budget_tokens: N}`; `->think(true)` / `AI_ANTHROPIC_THINK=true`. `max_tokens` is bumped automatically to stay above `budget_tokens`, and a custom `temperature` is dropped while thinking is enabled (the API rejects both otherwise).
- **Gemini** — `generationConfig.thinkingConfig.includeThoughts`; `->think(true)` / `AI_GEMINI_THINK=true`. Reasoning parts arrive flagged `thought:true` in the same `parts` array as the answer and are routed out of both the streamed chunks and the non-streaming response content.
- **OpenAI** — not supported: its Chat Completions API (what this package uses for OpenAI) doesn't expose reasoning content for o-series models at all, by design. Would need a separate Responses API driver to ever show anything here.

Same "thinking" vs "content" chunk-type contract and reflection-based callback-arity safety as Ollama's implementation, verified with 6 new tests.

### 🧠 Reasoning-model ("thinking") visibility

Reasoning models (qwen3 and similar) stream a separate `thinking` field ahead of the real answer — often 10–30+ seconds of nothing visible at all, which reads as a hung request. Found live against a real Ollama deployment: a plain "hello" took 40 seconds end-to-end, ~22 of it silent.

- `OllamaDriver::stream()` now forwards `thinking` chunks distinctly from `content` chunks (2nd callback argument) — the built-in chat UI renders them live as a collapsible **"🧠 Thinking… Ns"** block that auto-collapses to "Thought for Ns" once the real answer starts, instead of a silent gap.
- New `->think(bool)` builder method + `AI_OLLAMA_THINK` config to skip reasoning mode entirely when you'd rather have a faster, non-reasoning response.
- Callback compatibility is arity-checked via reflection: a single-parameter callback (the documented `stream()` pattern used before this release) never receives `thinking` chunks at all, rather than silently having reasoning text merged into its content — verified by a regression test.

---

## v2.0.0 — 2026-08-13

### 🔒 Security & Trust Suite

The Laravel equivalent of `easyit-ai-chat`'s v2.0 Security Suite, config/env-driven instead of a settings database:

- **Rate limiting** — per-identity + per-IP, via Laravel's `RateLimiter` (`AI_CHAT_RATE_LIMIT_*`)
- **Access restriction** — everyone / auth / role / a named `Gate` (`AI_CHAT_ACCESS_RESTRICTION`)
- **IP blocklist**, **word filter**, **prompt-injection detection** (block or warn)
- **No-storage mode** — `AI_CHAT_DISABLE_STORAGE=true` skips all DB writes for a request
- **Math captcha** — signed, stateless, required once per fresh session
- **Abuse-alert email** on rate-limit trips (via `Mail`)
- **GDPR consent gate** — cookie-based accept banner before the chat activates
- **Lock system prompt**, **configurable message-length cap**
- **Public-safe error messages** — internal exception detail hidden from non-admins by default

### 💬 Chat UX

Stop & regenerate, message timestamps, per-message + whole-conversation export, read-aloud (TTS via `SpeechSynthesis`), voice input (`SpeechRecognition`), fullscreen mode, 👍/👎 message feedback (persisted, ownership-checked), and client-side session search.

### 🎨 Personalization & embedding

Welcome message, suggested-question chips, custom AI avatar, accent/bubble color tokens, named **bot profiles** (`config('ai.chat.bot_profiles')`), and a new embeddable **`<x-laravelai::widget />`** Blade component — a self-contained floating launcher + chat panel any host view can drop in, independent of the full `/ai-chat` app.

### 🤖 New providers

**Google Gemini**, **Together AI** (including `/image` and `/img` FLUX image generation), and named **custom OpenAI-compatible providers** (`AI::custom('lmstudio')` / `AI::provider('custom:lmstudio')`) — LM Studio, vLLM, OpenRouter, or any in-house gateway.

### 📎 Attachments & vision

Image + document uploads in chat. Documents are text-extracted and appended as context for every provider; images become vision input for OpenAI/Anthropic/Gemini via a new universal multipart message format (`MessageFormatter::withImage()` / `toProviderContent()`), with a "can't view images" fallback note for the rest.

### 📊 Analytics & webhooks

A zero-external-tracking `/ai-chat/analytics` dashboard (conversations, messages, 7-day chart, feedback stats — all from existing tables), and a fire-and-forget webhook after each AI response with an optional HMAC-SHA256 signature header.

### 🧠 RAG refinements

Full-corpus **accurate counting** for "how many X" questions (`RAGManager::countMatches()`), an admin **test-query** endpoint, **in-place reprocessing** of an uploaded file without re-uploading, and an opt-in **auto-indexer** (`config('ai.rag.auto_index')`) that keeps RAG in sync with any host-app Eloquent model on save/delete — the Laravel equivalent of "Ask This Site."

### Identity & session scoping (foundational)

Chat sessions are now scoped per identity — an authenticated user id, or a signed guest cookie (`ChatIdentity`) — mirroring how the WordPress plugin never leaks sessions across visitors. Sessions created before this migration (no identity recorded) remain visible rather than disappearing.

### Fixes

- **Routes now run inside the `web` middleware group** (`Route::middleware('web')->group(...)` in `ChatServiceProvider`) — previously `loadRoutesFrom()` registered them with no middleware at all, so `$request->session()` (used for provider switching) had no session store bound, and CSRF verification never actually ran.
- **`/ai-chat/api/stream` is now a POST**, not GET-with-a-query-string — a 4000-character message no longer risks exceeding URL length limits, and the client reads the response via `fetch()` + a manual SSE parser instead of `EventSource`, so blocked/rate-limited JSON error responses (which aren't `text/event-stream`) are actually readable instead of silently failing `onerror` with no message.

**New package files:** `src/Chat/Support/{ChatIdentity,ChatGuard,TextExtractor}.php`, `src/Chat/Exceptions/ChatBlockedException.php`, `src/Chat/Models/ChatAttachment.php`, `src/Chat/Controllers/{ChatAttachmentController,AnalyticsController}.php`, `src/Drivers/{GeminiDriver,TogetherDriver,CustomDriver}.php`, `src/RAG/AutoIndexer.php`, `resources/views/analytics.blade.php`, `resources/views/components/widget.blade.php`, plus three additive migrations (session identity, message rating, chat attachments).

---

## v1.4.0 — 2026-05-10

### 🗂️ Projects + RAG Scoping (Self-hosted Claude Projects)

A full Projects system built into the Chat UI — create knowledge bases, upload documents, and chat with RAG-powered context scoped per project. Mirrors how Claude.ai Projects works, fully self-hosted.

**New features:**
- **Projects sidebar** — create, open, and delete projects from the Chat UI
- **File manager per project** — upload `.txt`, `.md`, `.pdf` files; auto-ingested into RAG on upload
- **Scoped RAG** — `AI::rag()->source('project_5')->search($query)` retrieves only that project's documents
- **RAG auto-injection** — project chat sessions automatically prepend RAG context before streaming
- **RAG badge** — header shows `🧠 RAG ON` when chatting inside a project session
- **Project-aware sessions** — sessions linked to a project via `project_id`; normal sessions unaffected
- **Safe project delete** — deletes RAG vectors, files, and sessions cleanly
- **PDF support** — optional via `composer require smalot/pdfparser`

**Bug fixes:**
- `forgetDrivers()` after embed call — prevents `nomic-embed-text` model bleeding into chat requests
- `findOrFail()` instead of route model binding — fixes 500 error for package-namespaced models
- Explicit `->model()` on every AI call — prevents shared driver state mutation
- `ob_get_level()` check before `ob_flush()` — fixes "headers already sent" on Apache with output buffering
- `num_ctx=2048` option for qwen2 models — fixes 400 error from context size too large
- Correct migration timestamps — fixes dependency order (projects before chat_sessions FK)

**New package files:**
- `src/Chat/Models/Project.php`
- `src/Chat/Models/ProjectFile.php`
- `src/Chat/Controllers/ProjectController.php`
- `src/Chat/Controllers/ProjectFileController.php`
- `database/migrations/2026_08_05_000001_create_ai_documents_table.php`
- `database/migrations/2026_08_06_000000_create_projects_and_files_tables.php`
- `database/migrations/2026_08_06_000001_add_project_id_to_chat_sessions.php`

**Updated files:**
- `src/RAG/RAGManager.php` — `source()` scoping, `forgetDrivers()` fix, scoped `flush()`
- `src/Chat/Models/ChatSession.php` — `project_id` fillable + `project()` relation
- `src/Chat/Controllers/AIChatController.php` — RAG injection, explicit model, ob_flush fix
- `routes/chat.php` — project and file routes
- `resources/views/chat.blade.php` — Projects UI, file manager modal, RAG indicators

**RAG API additions:**
```php
AI::rag()->source('project_5')->search($query);
AI::rag()->source('project_5')->ask($question);
AI::rag()->flush('project_5');
```

---

## v1.3.0 — 2026-05-08

### 💬 Built-in Chat UI (Zero Setup)

- Built-in chat UI at `/ai-chat`
- ChatGPT-like sidebar with session management
- Full Markdown rendering with syntax-highlighted code blocks
- Streaming responses with real-time typing effect
- Live provider switcher (Ollama, OpenAI, Claude, DeepSeek)
- Database-persisted conversation history
- Auto-title on first message
- Offline-safe assets (no CDN)
- Views and assets publishable

---

## v1.2.0 — 2026-05-03

### 🧠 Built-in RAG System + Ollama Advanced Features

- `AI::rag()->ingest()`, `search()`, `ask()`, `flush()`
- No external vector DB — uses SQL database
- Artisan command: `php artisan ai:rag:ingest`
- Ollama: JSON mode, embeddings, keepAlive, model management

---

## v1.1.0 — 2026-05-02

### Laravel 12 & 13 Support

- Confirmed compatibility with Laravel 12 and 13
- Updated CI matrix PHP 8.3, 8.4

---

## v1.0.0 — 2026-05-01

### Initial Release

- Ollama, OpenAI, Anthropic, DeepSeek drivers
- Unified `AIResponse` object
- Streaming, token estimation, health check
- Laravel Facade + `ai()` helper
- Custom driver support
