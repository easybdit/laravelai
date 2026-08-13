# Changelog

## v2.0.0 — 2026-08-13

### 🛒 Commerce Assistants (schema-agnostic)

Three new endpoints — "Ask Your Store" (admin analytics), Product Q&A/Finder, and Order Status — built entirely on three PHP interfaces (`src/Commerce/Contracts/{ProductResolver,OrderResolver,StoreAnalyticsResolver}.php`) instead of any concrete e-commerce schema. **Creates zero database tables** — nothing to collide with an existing site's own catalog/orders, WooCommerce, or any other package. Bind your own resolver implementation in your app's service provider; any endpoint whose resolver isn't bound returns a clear `501` instead of guessing at a schema. The Store Assistant is fail-closed by design — refused unless the host app explicitly defines `Gate::define('view-store-assistant', ...)`.

**New package files:** `src/Commerce/Contracts/{ProductResolver,OrderResolver,StoreAnalyticsResolver}.php`, `src/Commerce/Support/{StructuredResponseParser,StorePrompts}.php`, `src/Commerce/Controllers/{StoreAssistantController,ProductAssistantController,OrderStatusController}.php`, `src/Commerce/CommerceServiceProvider.php`, `routes/commerce.php`.

### 🧹 Namespace hygiene (pre-release)

Renamed the `projects` / `project_files` tables to `ai_projects` / `ai_project_files` — the bare names were a near-certain collision with any host app's own "projects" concept. Safe to change in place (not a rename migration): this table was never part of a tagged release, so no installed site has ever created it under the old name. If you're running off `main` pre-release and already migrated, drop and re-migrate rather than upgrading in place.

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
