# Changelog

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
