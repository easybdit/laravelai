<p align="center">
  <img src="https://raw.githubusercontent.com/easybdit/laraveleasyai/main/art/banner.svg" width="100%" alt="LaravelAI Banner">
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
  <a href="https://github.com/easybdit/laraveleasyai/actions"><img src="https://img.shields.io/github/actions/workflow/status/easybdit/laraveleasyai/tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-built-in-chat-ui">Chat UI</a> •
  <a href="#-projects--knowledge-bases">Projects</a> •
  <a href="#-rag-built-in">RAG</a> •
  <a href="#-providers">Providers</a> •
  <a href="#-api-reference">API Reference</a> •
  <a href="#%EF%B8%8F-configuration">Configuration</a>
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

### Customize the view

```bash
php artisan vendor:publish --tag=ai-chat-views
# → resources/views/vendor/laravelai/chat.blade.php
```

### Routes registered automatically

| Method | URL | Description |
|--------|-----|-------------|
| GET    | `/ai-chat` | Chat UI |
| POST   | `/ai-chat/api/sessions` | Create session |
| DELETE | `/ai-chat/api/sessions/{id}` | Delete session |
| GET    | `/ai-chat/api/stream` | SSE streaming |
| POST   | `/ai-chat/api/provider` | Switch provider |
| GET    | `/ai-chat/api/projects` | List projects |
| POST   | `/ai-chat/api/projects` | Create project |
| DELETE | `/ai-chat/api/projects/{id}` | Delete project |
| POST   | `/ai-chat/api/projects/{id}/files` | Upload & ingest file |
| DELETE | `/ai-chat/api/projects/{id}/files/{fid}` | Delete file |

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

### RAG Configuration

| `.env` Key | Default | Description |
|------------|---------|-------------|
| `AI_RAG_PROVIDER` | `ollama` | Embedding provider |
| `AI_RAG_EMBED_MODEL` | `nomic-embed-text` | Embedding model |
| `AI_RAG_CHUNK_SIZE` | `2000` | Max chars per chunk |
| `AI_RAG_TOP_K` | `3` | Chunks retrieved per query |
| `AI_RAG_TABLE` | `ai_documents` | Database table |

---

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

### DeepSeek

```env
AI_DEEPSEEK_KEY=sk-your-api-key
AI_DEEPSEEK_MODEL=deepseek-chat
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
| `AI::provider(string $name)` | `AIProvider` | Switch provider |
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

### Ollama-Only Methods

| Method | Description |
|--------|-------------|
| `->format('json')` | Force JSON output |
| `->embed($text)` | Generate embedding |
| `->keepAlive($duration)` | Keep in memory |
| `->options($array)` | Raw Ollama options (e.g. `num_ctx`) |
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

# OpenAI
AI_OPENAI_KEY=sk-proj-xxxx
AI_OPENAI_MODEL=gpt-4o-mini

# Anthropic (Claude)
AI_ANTHROPIC_KEY=sk-ant-xxxx
AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514

# DeepSeek
AI_DEEPSEEK_KEY=sk-xxxx
AI_DEEPSEEK_MODEL=deepseek-chat

# RAG
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHUNK_SIZE=500
AI_RAG_TOP_K=1
AI_RAG_TABLE=ai_documents

# RAG for small models — reduce chunk size and limit context
# AI_OLLAMA_NUM_CTX=2048
```

---

## 🧪 Testing

```bash
vendor/bin/phpunit
vendor/bin/phpunit --filter=test_ollama_chat
```

Uses `Http::fake()` — no real API calls needed.

---

## 🗺️ Roadmap

| Version | Feature | Status |
|---------|---------|--------|
| v1.0 | Ollama, OpenAI, Anthropic, DeepSeek | ✅ Released |
| v1.1 | Laravel 12 & 13 support | ✅ Released |
| v1.2 | Built-in RAG system + Ollama advanced | ✅ Released |
| v1.3 | Built-in Chat UI | ✅ Released |
| v1.4 | Projects + RAG scoping (self-hosted Claude Projects) | ✅ Released |
| v2.0 | Function / Tool calling | 🔜 Planned |
| v2.0 | Vision / Image input | 🔜 Planned |
| v2.1 | Groq driver | 🔜 Planned |
| v2.1 | Google Gemini driver | 🔜 Planned |
| v2.2 | Response caching | 🔜 Planned |
| v3.0 | Image generation | 🔜 Planned |

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
- 🐛 **Report bugs** via [Issues](https://github.com/easybdit/laraveleasyai/issues)
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
