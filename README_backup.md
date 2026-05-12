<p align="center">
  <img src="https://raw.githubusercontent.com/muradbdinfo/laravelai/main/art/banner.svg" width="100%" alt="LaravelAI Banner">
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
  <a href="https://packagist.org/packages/muradbdinfo/laravelai"><img src="https://img.shields.io/packagist/v/muradbdinfo/laravelai.svg?style=flat-square&label=version" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/muradbdinfo/laravelai"><img src="https://img.shields.io/packagist/dt/muradbdinfo/laravelai.svg?style=flat-square&label=downloads" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/muradbdinfo/laravelai"><img src="https://img.shields.io/packagist/l/muradbdinfo/laravelai.svg?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/muradbdinfo/laravelai"><img src="https://img.shields.io/packagist/php-v/muradbdinfo/laravelai.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://github.com/muradbdinfo/laravelai/actions"><img src="https://img.shields.io/github/actions/workflow/status/muradbdinfo/laravelai/tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-providers">Providers</a> •
  <a href="#-features">Features</a> •
  <a href="#-rag-built-in">RAG</a> •
  <a href="#-real-world-examples">Examples</a> •
  <a href="#-chat-application-integration">Chat App</a> •
  <a href="#-api-reference">API Reference</a> •
  <a href="#-configuration">Configuration</a>
</p>

<p align="center">
  <a href="https://www.facebook.com/easybdit">📘 Facebook Page</a> •
  <a href="https://www.facebook.com/groups/eitbd">👥 Facebook Group</a> •
  <a href="https://chat.whatsapp.com/E3VV0K6lkrqEgXdngrt2Rk">💬 WhatsApp Group</a>
</p>

## 📺 Video Tutorials

<table>
  <tr>
    <td align="center" width="50%">
      <a href="https://youtu.be/m_HyTIBRAOE">
        <img src="https://img.youtube.com/vi/m_HyTIBRAOE/maxresdefault.jpg" 
             alt="How to make self hosted AI Server" width="100%">
        <br><br>
        <strong>🖥️ How to Make Self-Hosted AI Server</strong>
      </a>
      <br>
      <sub>Step-by-step guide to setting up your own local AI server with Ollama</sub>
    </td>
    <td align="center" width="50%">
      <a href="https://youtu.be/pSwewtXqgP8">
        <img src="https://img.youtube.com/vi/pSwewtXqgP8/maxresdefault.jpg" 
             alt="Laravel AI Package Implement" width="100%">
        <br><br>
        <strong>🚀 Laravel AI Package Implementation</strong>
      </a>
      <br>
      <sub>How to install and use LaravelAI package in your Laravel project</sub>
    </td>
  </tr>
</table>

---

## Why LaravelAI?

Building AI features in Laravel? You'd normally need separate SDKs, different request formats, and custom error handling for each provider. **LaravelAI eliminates all that.**

```php
// Same code works with ANY provider — just change the name
$response = AI::provider('ollama')->chat($messages);    // Self-hosted
$response = AI::provider('openai')->chat($messages);    // ChatGPT
$response = AI::provider('anthropic')->chat($messages); // Claude
$response = AI::provider('deepseek')->chat($messages);  // DeepSeek
```

**Built on Laravel's driver pattern** — the same architecture behind Mail, Cache, and Queue. If you know Laravel, you already know how to use this.

---

## 📦 Installation

**Step 1:** Install via Composer

```bash
composer require muradbdinfo/laravelai
```

**Step 2:** Publish the config file

```bash
php artisan vendor:publish --tag=ai-config
```

**Step 3:** Add your provider settings to `.env`

```env
AI_PROVIDER=ollama
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
```

**That's it!** You're ready to use AI in your Laravel app.

### Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| Laravel | 10, 11, 12, 13 |

---

## 🚀 Quick Start

### The Simplest Example — 3 Lines

```php
use EasyAI\LaravelAI\Facades\AI;

$response = AI::chat([
    ['role' => 'user', 'content' => 'What is Laravel?']
]);

echo $response->content;
// "Laravel is a PHP web application framework..."
```

### One-Liner Helper

```php
$answer = ai('What is Laravel?');
// Returns the AI response as a string — that's it!
```

### Test It Right Now

```bash
php artisan tinker

>>> AI::provider('ollama')->health()
=> true

>>> ai('Say hello in 3 words')
=> "Hello there, friend!"
```

---

## 🤖 Providers

LaravelAI supports 4 providers out of the box. Set up as many as you need.

### Ollama (Self-Hosted — Free)

Run AI models on your own server. No API key needed.

```env
AI_PROVIDER=ollama
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
```

```php
$response = AI::provider('ollama')->chat([
    ['role' => 'user', 'content' => 'Hello!']
]);
```

### OpenAI (ChatGPT)

```env
AI_OPENAI_KEY=sk-your-api-key
AI_OPENAI_MODEL=gpt-4o-mini
```

```php
$response = AI::provider('openai')
    ->model('gpt-4o')
    ->chat([['role' => 'user', 'content' => 'Hello!']]);
```

### Anthropic (Claude)

```env
AI_ANTHROPIC_KEY=sk-ant-your-api-key
AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

```php
$response = AI::provider('anthropic')->chat([
    ['role' => 'user', 'content' => 'Hello!']
]);
```

### DeepSeek

```env
AI_DEEPSEEK_KEY=sk-your-api-key
AI_DEEPSEEK_MODEL=deepseek-chat
```

```php
$response = AI::provider('deepseek')->chat([
    ['role' => 'user', 'content' => 'Hello!']
]);
```

---

## ✨ Features

### 1. Chain Options

Customize every aspect of your AI request with a fluent API:

```php
$response = AI::provider('ollama')
    ->model('qwen2:1.5b')        // Choose model
    ->temperature(0.9)             // Creativity (0 = focused, 2 = creative)
    ->maxTokens(500)               // Limit response length
    ->systemPrompt('You are a helpful Laravel expert.')
    ->chat([
        ['role' => 'user', 'content' => 'Explain middleware']
    ]);
```

### 2. System Prompts — Give AI a Persona

```php
// Make it a pirate
$response = AI::systemPrompt('You are a pirate captain. Always talk like a pirate.')
    ->chat([['role' => 'user', 'content' => 'Tell me about PHP']]);

// "Arrr! PHP be a fine language for sailin' the web seas, matey!"
```

### 3. Multi-Turn Conversations

Send full conversation history for context-aware responses:

```php
$response = AI::chat([
    ['role' => 'system', 'content' => 'You are a math tutor.'],
    ['role' => 'user', 'content' => 'What is 2+2?'],
    ['role' => 'assistant', 'content' => '2+2 equals 4.'],
    ['role' => 'user', 'content' => 'Now multiply that by 10'],
]);

// "4 multiplied by 10 equals 40."
```

### 4. Streaming — Real-Time Output

Get tokens as they are generated, just like ChatGPT typing effect:

```php
AI::provider('ollama')->stream(
    [['role' => 'user', 'content' => 'Write a poem about coding']],
    function (string $chunk) {
        echo $chunk; // Each word appears in real-time
    }
);
```

**Use in Laravel HTTP response (SSE):**

```php
// In your Controller
public function stream(Request $request)
{
    return response()->stream(function () use ($request) {
        AI::provider('ollama')->stream(
            [['role' => 'user', 'content' => $request->message]],
            function (string $chunk) {
                echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                ob_flush();
                flush();
            }
        );
        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
}
```

### 5. Health Check

Check if a provider is online before sending requests:

```php
if (AI::provider('ollama')->health()) {
    $response = AI::chat($messages);
} else {
    // Fallback to another provider
    $response = AI::provider('openai')->chat($messages);
}
```

### 6. List Available Models

```php
$models = AI::provider('ollama')->models();
// ['qwen2:1.5b', 'qwen2:1.5b', 'phi3:mini']

$models = AI::provider('openai')->models();
// ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', ...]
```

### 7. Token Estimation

Estimate token count **before** sending to AI — no API call needed:

```php
// Estimate a string
$tokens = AI::estimateTokens('Hello world, how are you?');
// 8

// Estimate a full conversation
$tokens = AI::estimateTokens([
    ['role' => 'system', 'content' => 'You are helpful.'],
    ['role' => 'user', 'content' => 'Explain PHP in 100 words'],
]);
// ~25
```

### 8. Error Handling

LaravelAI throws specific exceptions you can catch:

```php
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;

try {
    $response = AI::provider('openai')->chat($messages);
} catch (ConnectionException $e) {
    // Provider is unreachable
    Log::error("AI connection failed: " . $e->getMessage());
} catch (ProviderException $e) {
    // API returned an error (401, 429, 500, etc.)
    Log::error("AI error [{$e->getProvider()}]: " . $e->getMessage());
}
```

---

## 🧠 RAG (Built-in)

LaravelAI includes a built-in RAG (Retrieval-Augmented Generation) system — store documents as embeddings, search by similarity, and let AI answer questions using your own data. No external vector database required.

### Setup

```bash
# 1. Pull an embedding model
ollama pull nomic-embed-text

# 2. Run migrations — creates ai_documents table automatically
php artisan migrate
```

Add to `.env`:

```env
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHAT_PROVIDER=ollama
```

### Ingest Documents

```php
// Ingest a string
AI::rag()->ingest('Laravel is a PHP framework using MVC pattern.', 'docs');

// Ingest via Artisan
php artisan ai:rag:ingest storage/docs/manual.txt --source=manual
php artisan ai:rag:ingest storage/docs/ --flush
```

### Ask Questions

```php
$answer = AI::rag()->ask('What is Laravel?');
// "Laravel is a PHP framework using MVC pattern..."
```

### Search (without AI)

```php
$results = AI::rag()->search('MVC pattern');
// [['content' => '...', 'source' => 'docs', 'score' => 0.91]]
```

### Flush All Documents

```php
AI::rag()->flush();
```

### RAG in a Controller

```php
class DocsController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate(['question' => 'required|string']);

        return response()->json([
            'answer' => AI::rag()->ask($request->question),
        ]);
    }
}
```

### RAG Configuration

```php
'rag' => [
    'embed_provider' => env('AI_RAG_PROVIDER', 'ollama'),
    'embed_model'    => env('AI_RAG_EMBED_MODEL', 'nomic-embed-text'),
    'chat_provider'  => env('AI_RAG_CHAT_PROVIDER', null),  // null = use AI_PROVIDER default
    'chunk_size'     => (int) env('AI_RAG_CHUNK_SIZE', 2000),
    'top_k'          => (int) env('AI_RAG_TOP_K', 3),
    'table'          => env('AI_RAG_TABLE', 'ai_documents'),
    'system_prompt'  => env('AI_RAG_SYSTEM_PROMPT', 'Answer using ONLY the context below. If unsure, say so.'),
],
```

| `.env` Key | Default | Description |
|------------|---------|-------------|
| `AI_RAG_PROVIDER` | `ollama` | Provider for generating embeddings |
| `AI_RAG_EMBED_MODEL` | `nomic-embed-text` | Embedding model |
| `AI_RAG_CHAT_PROVIDER` | `null` (uses default) | Provider for chat in `ask()` |
| `AI_RAG_CHUNK_SIZE` | `2000` | Max characters per chunk |
| `AI_RAG_TOP_K` | `3` | Chunks to retrieve per query |
| `AI_RAG_TABLE` | `ai_documents` | Database table name |

---

## 💡 Real-World Examples

### AI Chatbot Controller

```php
class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $response = AI::chat([
            ['role' => 'user', 'content' => $request->message],
        ]);

        return response()->json([
            'reply'  => $response->content,
            'tokens' => $response->totalTokens,
        ]);
    }
}
```

### AI Translator

```php
public function translate(Request $request)
{
    $response = AI::systemPrompt(
        "Translate to {$request->language}. Return ONLY the translation."
    )->chat([
        ['role' => 'user', 'content' => $request->text],
    ]);

    return response()->json(['translated' => $response->content]);
}
```

### AI Code Reviewer

```php
public function review(Request $request)
{
    $response = AI::systemPrompt(
        'You are a senior code reviewer. Find bugs, security issues, and suggest improvements.'
    )->chat([
        ['role' => 'user', 'content' => "Review:\n```\n{$request->code}\n```"],
    ]);

    return response()->json(['review' => $response->content]);
}
```

### AI Email Writer

```php
public function writeEmail(Request $request)
{
    $response = AI::systemPrompt(
        "Write a {$request->tone} email. Include subject and body."
    )->chat([
        ['role' => 'user', 'content' => $request->context],
    ]);

    return response()->json(['email' => $response->content]);
}
```

### AI Content Summarizer

```php
public function summarize(Request $request)
{
    $response = AI::systemPrompt(
        'Summarize the following text in 3 bullet points. Be concise.'
    )->chat([
        ['role' => 'user', 'content' => $request->text],
    ]);

    return response()->json(['summary' => $response->content]);
}
```

### Provider Fallback Pattern

```php
public function chatWithFallback(string $message): string
{
    $providers = ['ollama', 'deepseek', 'openai'];

    foreach ($providers as $provider) {
        try {
            if (!AI::provider($provider)->health()) continue;

            return AI::provider($provider)
                ->chat([['role' => 'user', 'content' => $message]])
                ->content;
        } catch (\Throwable $e) {
            Log::warning("Provider {$provider} failed: {$e->getMessage()}");
            continue;
        }
    }

    throw new \RuntimeException('All AI providers are unavailable');
}
```

### Use in Artisan Command

```php
class AskAI extends Command
{
    protected $signature = 'ai:ask {question}';
    protected $description = 'Ask AI a question from terminal';

    public function handle()
    {
        $this->info('Thinking...');

        $answer = ai($this->argument('question'));

        $this->line($answer);
    }
}
```

```bash
php artisan ai:ask "What is dependency injection?"
```

### Use in Blade Template (via Controller)

```php
// Controller
public function about()
{
    $tagline = ai('Write a 10-word tagline for a Laravel AI package');
    return view('about', compact('tagline'));
}
```

```html
<!-- Blade -->
<p class="tagline">{{ $tagline }}</p>
```

---

## 🤖 Chat Application Integration

Complete AI chat interface built with LaravelAI + Ollama. Features dual-mode chat (normal + streaming) with a modern responsive UI.

### 📁 File Structure

```
easyjobs/
├── app/
│   └── Http/
│       └── Controllers/
│           └── AIChatController.php      # Chat & Stream handlers
├── resources/
│   └── views/
│       └── ai-test.blade.php             # Chat UI (HTML/CSS/JS)
├── routes/
│   └── web.php                           # Routes definition
└── .env                                  # Ollama configuration
```

### ⚙️ Environment Configuration

```env
AI_PROVIDER=ollama
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
AI_OLLAMA_TIMEOUT=120
```

> **Note:** `127.0.0.1` for local Ollama. For remote server use `192.168.x.x` or appropriate IP.

### 🎮 Step 1: Create Controller

```bash
php artisan make:controller AIChatController
```

**`app/Http/Controllers/AIChatController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Exceptions\ConnectionException;

class AIChatController extends Controller
{
    /**
     * Normal chat — returns full response with token stats.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000'
        ]);

        try {
            $response = AI::provider('ollama')
                ->timeout(120)
                ->chat([
                    ['role' => 'user', 'content' => $request->message]
                ]);

            return response()->json([
                'reply'  => $response->content,
                'model'  => $response->model,
                'tokens' => $response->totalTokens
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'error'   => 'Ollama server connection failed',
                'message' => $e->getMessage(),
                'check'   => 'Run: ollama serve (ensure 127.0.0.1:11434 is accessible)'
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'AI request failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Streaming chat — Server-Sent Events (SSE) for real-time output.
     */
    public function stream(Request $request)
    {
        $request->validate(['message' => 'required|string']);

        return response()->stream(function () use ($request) {
            AI::provider('ollama')->stream(
                [['role' => 'user', 'content' => $request->message]],
                function (string $chunk) {
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }
            );
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
```

### 🛣️ Step 2: Routes

**`routes/web.php`**

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIChatController;

// Public routes
Route::get('/', fn () => view('welcome'));

// AI Chat API
Route::post('/ai/chat', [AIChatController::class, 'chat']);
Route::get('/ai/stream', [AIChatController::class, 'stream']);

// Chat UI
Route::get('/ai-test', fn () => view('ai-test'));
```

### 🎨 Step 3: Chat UI (ai-test.blade.php)

Create `resources/views/ai-test.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel AI Chat · Ollama Qwen2</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(145deg, #f1f5f9 0%, #e6edf4 100%);
            font-family: 'Inter', system-ui, sans-serif;
            padding: 1.5rem; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .chat-container {
            max-width: 1100px; width: 100%;
            background: rgba(255,255,255,0.92);
            border-radius: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 1.5rem 2rem; color: white;
        }
        .chat-header h1 {
            font-size: 1.7rem; font-weight: 600;
            display: flex; align-items: center; gap: 12px;
        }
        .badge-model {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.9rem; border-radius: 40px;
            font-size: 0.75rem; font-family: monospace;
        }
        .sub {
            font-size: 0.85rem; color: #cbd5e6;
            margin-top: 8px; display: flex; gap: 20px;
        }
        .chat-panel { padding: 1.8rem 2rem 2rem; }
        .input-group {
            background: #fff; border-radius: 1.75rem;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            transition: box-shadow 0.2s, border 0.2s;
        }
        .input-group:focus-within {
            border-color: #818cf8;
            box-shadow: 0 6px 18px rgba(99,102,241,0.15);
        }
        textarea {
            width: 100%; border: none; padding: 1.2rem 1.5rem;
            font-size: 1rem; background: transparent; resize: vertical;
            border-radius: 1.75rem; outline: none; line-height: 1.5;
            color: #0f172a;
        }
        .button-bar { display: flex; gap: 1rem; margin: 1rem 0 1.5rem; }
        .btn {
            border: none; padding: 0.7rem 1.8rem; border-radius: 3rem;
            font-weight: 600; font-size: 0.9rem; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary {
            background: #4f46e5; color: white;
            box-shadow: 0 4px 10px rgba(79,70,229,0.25);
        }
        .btn-primary:hover { background: #4338ca; transform: translateY(-2px); }
        .btn-outline {
            background: white; border: 1px solid #cbd5e1; color: #1f2937;
        }
        .btn-outline:hover { background: #f8fafc; border-color: #818cf8; }
        .response-area {
            background: #fefefe; border-radius: 1.5rem;
            border: 1px solid #eef2ff; overflow: hidden;
        }
        .response-header {
            background: #f8fafd; padding: 0.9rem 1.5rem;
            border-bottom: 1px solid #e9edf2;
            font-size: 0.85rem; font-weight: 600; color: #334155;
            display: flex; justify-content: space-between; align-items: center;
        }
        .model-tag {
            background: #eef2ff; padding: 0.2rem 0.7rem;
            border-radius: 20px; font-size: 0.7rem;
            font-family: monospace; color: #4f46e5;
        }
        .token-info { font-size: 0.7rem; background: #e9ecef; padding: 0.2rem 0.7rem; border-radius: 20px; }
        .reply-content {
            padding: 1.5rem; background: white;
            font-size: 1rem; line-height: 1.55; color: #0f172a;
            white-space: pre-wrap; word-break: break-word;
            min-height: 160px; max-height: 450px; overflow-y: auto;
        }
        .chat-footer {
            background: #f9fbfd; padding: 0.9rem 2rem;
            border-top: 1px solid #e9edf2;
            font-size: 0.7rem; color: #5b6e8c; text-align: center;
        }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="chat-container">
    <div class="chat-header">
        <h1>
            <span>🤖 Laravel AI Chat</span>
            <span class="badge-model">Ollama · qwen2:1.5b</span>
        </h1>
        <div class="sub">
            <span>⚡ Local LLM (127.0.0.1:11434)</span>
            <span>🔁 Streaming & Direct modes</span>
        </div>
    </div>
    <div class="chat-panel">
        <div class="input-group">
            <textarea id="message" placeholder="Ask anything...">Tell me the history of Laravel Framework</textarea>
        </div>
        <div class="button-bar">
            <button class="btn btn-primary" id="sendBtn" onclick="sendMessage()">✨ Send</button>
            <button class="btn btn-outline" id="streamBtn" onclick="streamMessage()">🌊 Stream</button>
            <button class="btn" onclick="clearResponse()" style="background:#fff;border:1px solid #e2e8f0;">🧹 Clear</button>
        </div>
        <div class="response-area">
            <div class="response-header">
                <span>💬 AI Response <span class="model-tag" id="modelDisplay">qwen2:1.5b</span></span>
                <span id="metaInfo" class="token-info">—</span>
            </div>
            <div class="reply-content" id="response">
                <div style="color:#94a3b8;font-style:italic;">✨ Your answer will appear here...</div>
            </div>
        </div>
    </div>
    <div class="chat-footer">
        ⚡ Powered by Laravel + Ollama (127.0.0.1:11434) | qwen2:1.5b
    </div>
</div>
<script>
    const csrfToken = '{{ csrf_token() }}';
    let activeEventSource = null;

    async function sendMessage() {
        const msg = document.getElementById('message').value.trim();
        if (!msg) { alert('Please enter a message'); return; }
        document.getElementById('response').innerHTML = '<div>🤔 Thinking...</div>';
        document.getElementById('sendBtn').disabled = true;
        try {
            const res = await fetch('/ai/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ message: msg })
            });
            const data = await res.json();
            if (data.error) throw new Error(data.message || data.error);
            document.getElementById('modelDisplay').innerText = data.model || 'qwen2:1.5b';
            document.getElementById('metaInfo').innerHTML = `🔢 tokens: ${data.tokens ?? 'N/A'}`;
            document.getElementById('response').innerHTML = `<div>${escapeHtml(data.reply)}</div>`;
        } catch (err) {
            document.getElementById('response').innerHTML = `<div style="color:#b91c1c;">⚠️ ${err.message}</div>`;
        } finally {
            document.getElementById('sendBtn').disabled = false;
        }
    }

    function streamMessage() {
        if (activeEventSource) activeEventSource.close();
        const msg = document.getElementById('message').value.trim();
        if (!msg) { alert('Please enter a message'); return; }
        document.getElementById('response').innerHTML = '';
        document.getElementById('streamBtn').disabled = true;
        let text = '';
        const evtSource = new EventSource(`/ai/stream?message=${encodeURIComponent(msg)}`);
        activeEventSource = evtSource;
        evtSource.onmessage = (e) => {
            if (e.data === '[DONE]') {
                evtSource.close(); activeEventSource = null;
                document.getElementById('streamBtn').disabled = false;
                document.getElementById('metaInfo').innerHTML = `✅ Done · ${text.length} chars`;
                return;
            }
            try {
                const data = JSON.parse(e.data);
                if (data.text) {
                    text += data.text;
                    document.getElementById('response').innerHTML = `<div style="white-space:pre-wrap;">${escapeHtml(text)}</div>`;
                }
            } catch (err) {}
        };
        evtSource.onerror = () => {
            evtSource.close(); activeEventSource = null;
            document.getElementById('streamBtn').disabled = false;
        };
    }

    function clearResponse() {
        if (activeEventSource) { activeEventSource.close(); activeEventSource = null; }
        document.getElementById('response').innerHTML = '<div style="color:#94a3b8;font-style:italic;">✨ Ready for new input!</div>';
        document.getElementById('metaInfo').innerText = '—';
        document.getElementById('sendBtn').disabled = false;
        document.getElementById('streamBtn').disabled = false;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                  .replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/\n/g,'<br>');
    }

    document.getElementById('message').addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); sendMessage(); }
    });
</script>
</body>
</html>
```

### 🚀 Step 4: Run & Test

```bash
# Terminal 1 — Start Ollama
ollama serve

# Terminal 2 — Start Laravel
cd easyjobs
php artisan serve
```

Visit: `http://localhost:8000/ai-test`

#### Test Cases

| Action | Expected Result |
|--------|----------------|
| Type message → **Send** | Full reply + token count |
| Type message → **Stream** | Real-time word-by-word output |
| **Clear** button | Resets response area |
| Empty message | Alert: "Please enter a message" |
| Ollama offline | Error: "Connection failed" |

### 🔧 Troubleshooting

| Problem | Solution |
|---------|----------|
| **Loading stuck** | Check `curl http://127.0.0.1:11434/api/tags` |
| **Connection refused** | Run `ollama serve` in terminal |
| **Model not found** | Run `ollama pull qwen2:1.5b` |
| **Slow first response** | qwen2:1.5b loads into memory; wait 10–30s |
| **Config not loading** | Run `php artisan config:clear` |

### Debug Route (Temporary)

Add to `routes/web.php`:

```php
Route::get('/debug-ollama', function () {
    try {
        return response()->json([
            'health' => AI::provider('ollama')->health(),
            'models' => AI::provider('ollama')->models(),
            'url'    => config('ai.providers.ollama.url'),
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
```

---

## 📖 API Reference

### Facade Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `AI::chat(array $messages)` | `AIResponse` | Send chat with default provider |
| `AI::provider(string $name)` | `AIProvider` | Switch to a specific provider |
| `AI::estimateTokens(string\|array $input)` | `int` | Estimate token count offline |
| `AI::rag()` | `RAGManager` | Access the built-in RAG system |

### Provider Methods (Chainable)

| Method | Description |
|--------|-------------|
| `->model('gpt-4o')` | Set the AI model |
| `->temperature(0.7)` | Set creativity (0–2) |
| `->maxTokens(500)` | Limit response length |
| `->systemPrompt('...')` | Set AI persona/instructions |
| `->timeout(30)` | Set request timeout in seconds |
| `->chat(array $messages)` | Send and get response |
| `->stream(array $messages, callable $fn)` | Stream response in real-time |
| `->health()` | Check if provider is online |
| `->models()` | List available models |

### Response Object

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$response->content` | `string` | The AI reply text |
| `$response->promptTokens` | `int` | Tokens in your message |
| `$response->completionTokens` | `int` | Tokens in AI reply |
| `$response->totalTokens` | `int` | Total tokens used |
| `$response->model` | `string` | Model that was used |
| `$response->provider` | `string` | Provider name |
| `$response->getRaw()` | `array` | Raw API response |
| `$response->toArray()` | `array` | All data as array |
| `(string) $response` | `string` | Cast to content string |

### Helper Function

```php
// Basic — uses default provider
ai('Your question here')

// With provider
ai('Your question', 'openai')

// With provider and model
ai('Your question', 'anthropic', 'claude-sonnet-4-20250514')
```

### Message Format

All providers use the same message format:

```php
$messages = [
    ['role' => 'system', 'content' => 'Your instructions'],    // Optional
    ['role' => 'user', 'content' => 'User message'],            // Required
    ['role' => 'assistant', 'content' => 'Previous AI reply'],  // For context
    ['role' => 'user', 'content' => 'Follow-up question'],
];
```

> **Note:** Anthropic (Claude) handles system messages differently — LaravelAI converts the format automatically. You don't need to worry about it.

---

## ⚙️ Configuration

Full `config/ai.php` reference:

```php
return [
    // Default provider when calling AI::chat() without specifying one
    'default' => env('AI_PROVIDER', 'ollama'),

    'providers' => [

        'ollama' => [
            'driver'  => 'ollama',
            'url'     => env('AI_OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model'   => env('AI_OLLAMA_MODEL', 'qwen2:1.5b'),
            'timeout' => (int) env('AI_OLLAMA_TIMEOUT', 120),
            'options' => ['temperature' => 0.7],
        ],

        'openai' => [
            'driver'  => 'openai',
            'api_key' => env('AI_OPENAI_KEY'),
            'url'     => env('AI_OPENAI_URL', 'https://api.openai.com/v1'),
            'model'   => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ],

        'anthropic' => [
            'driver'  => 'anthropic',
            'api_key' => env('AI_ANTHROPIC_KEY'),
            'url'     => env('AI_ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'model'   => env('AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'version' => env('AI_ANTHROPIC_VERSION', '2023-06-01'),
            'timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),
            'options' => ['max_tokens' => 2000],
        ],

        'deepseek' => [
            'driver'  => 'deepseek',
            'api_key' => env('AI_DEEPSEEK_KEY'),
            'url'     => env('AI_DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
            'model'   => env('AI_DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('AI_DEEPSEEK_TIMEOUT', 60),
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ],
    ],

    // Optional: log all AI requests
    'logging' => [
        'enabled' => (bool) env('AI_LOG_ENABLED', false),
        'channel' => env('AI_LOG_CHANNEL', 'stack'),
    ],

    // Retry failed requests
    'retry' => [
        'times' => (int) env('AI_RETRY_TIMES', 2),
        'sleep' => (int) env('AI_RETRY_SLEEP', 1000), // milliseconds
    ],
];
```

### Complete `.env` Example

```env
# Default provider
AI_PROVIDER=ollama

# Ollama (self-hosted, free)
AI_OLLAMA_URL=http://127.0.0.1:11434
AI_OLLAMA_MODEL=qwen2:1.5b
AI_OLLAMA_TIMEOUT=120

# OpenAI (ChatGPT)
AI_OPENAI_KEY=sk-proj-xxxx
AI_OPENAI_MODEL=gpt-4o-mini
AI_OPENAI_TIMEOUT=60

# Anthropic (Claude)
AI_ANTHROPIC_KEY=sk-ant-xxxx
AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514
AI_ANTHROPIC_TIMEOUT=60

# DeepSeek
AI_DEEPSEEK_KEY=sk-xxxx
AI_DEEPSEEK_MODEL=deepseek-chat
AI_DEEPSEEK_TIMEOUT=60

# RAG (built-in)
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHAT_PROVIDER=ollama
AI_RAG_CHUNK_SIZE=2000
AI_RAG_TOP_K=3
AI_RAG_TABLE=ai_documents

# RAG (built-in)
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHAT_PROVIDER=ollama
AI_RAG_CHUNK_SIZE=2000
AI_RAG_TOP_K=3
AI_RAG_TABLE=ai_documents

# Logging (optional)
AI_LOG_ENABLED=false
AI_LOG_CHANNEL=stack
```

---

## 🔌 Add Your Own Provider

LaravelAI uses Laravel's Manager pattern — adding a custom provider is simple:

```php
// 1. Create your driver class
class GroqDriver extends AbstractDriver
{
    public function getProviderName(): string { return 'groq'; }

    public function chat(array $messages): AIResponseInterface
    {
        // Your implementation here
    }

    public function health(): bool { /* ... */ }
    public function models(): array { /* ... */ }
}

// 2. Register in AppServiceProvider@boot
AI::extend('groq', function ($config) {
    return new GroqDriver($config);
});

// 3. Add config in config/ai.php providers
'groq' => [
    'driver'  => 'groq',
    'api_key' => env('AI_GROQ_KEY'),
    'url'     => 'https://api.groq.com/openai/v1',
    'model'   => 'llama-3.1-70b-versatile',
    'timeout' => 30,
],

// 4. Use it!
AI::provider('groq')->chat($messages);
```

---

## 🧪 Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit --filter=test_ollama_chat
```

The package uses `Http::fake()` for testing — no real API calls needed:

```php
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Support\Facades\Http;

Http::fake([
    '127.0.0.1:11434/api/chat' => Http::response([
        'message' => ['content' => 'Mocked response'],
        'model' => 'qwen2:1.5b',
        'prompt_eval_count' => 10,
        'eval_count' => 5,
        'done' => true,
    ]),
]);

$response = AI::chat([['role' => 'user', 'content' => 'Hi']]);
$this->assertEquals('Mocked response', $response->content);
```

---

## 🧠 RAG (Built-in)

LaravelAI includes a built-in RAG (Retrieval-Augmented Generation) system. Store documents as embeddings, search by similarity, and let AI answer questions using your own data — no external vector database needed.

### Setup

```bash
# 1. Pull an embedding model (Ollama)
ollama pull nomic-embed-text

# 2. Migrations run automatically — just run:
php artisan migrate
```

Add to `.env`:

```env
AI_RAG_PROVIDER=ollama
AI_RAG_EMBED_MODEL=nomic-embed-text
AI_RAG_CHAT_PROVIDER=ollama
```

### Ingest Documents

```php
use EasyAI\LaravelAI\Facades\AI;

// Ingest a string
AI::rag()->ingest('Laravel is a PHP framework using MVC pattern.', 'docs');

// Ingest via Artisan command
// php artisan ai:rag:ingest storage/docs/manual.txt --source=manual
// php artisan ai:rag:ingest storage/docs/ --flush
```

### Ask Questions

```php
$answer = AI::rag()->ask('What is Laravel?');
// "Laravel is a PHP framework using MVC pattern..."
```

### Search (without AI)

```php
// Returns top-K relevant chunks with similarity scores
$results = AI::rag()->search('MVC pattern');
// [['content' => '...', 'source' => 'docs', 'score' => 0.91]]
```

### Flush All Documents

```php
AI::rag()->flush();
```

### RAG in a Controller

```php
class DocsController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate(['question' => 'required|string']);

        return response()->json([
            'answer' => AI::rag()->ask($request->question),
        ]);
    }
}
```

### RAG Configuration

```php
'rag' => [
    'embed_provider' => env('AI_RAG_PROVIDER', 'ollama'),
    'embed_model'    => env('AI_RAG_EMBED_MODEL', 'nomic-embed-text'),
    'chat_provider'  => env('AI_RAG_CHAT_PROVIDER', null),
    'chunk_size'     => (int) env('AI_RAG_CHUNK_SIZE', 2000),
    'top_k'          => (int) env('AI_RAG_TOP_K', 3),
    'table'          => env('AI_RAG_TABLE', 'ai_documents'),
    'system_prompt'  => env('AI_RAG_SYSTEM_PROMPT', 'Answer using ONLY the context below. If unsure, say so.'),
],
```

| `.env` Key | Default | Description |
|------------|---------|-------------|
| `AI_RAG_PROVIDER` | `ollama` | Provider for generating embeddings |
| `AI_RAG_EMBED_MODEL` | `nomic-embed-text` | Embedding model |
| `AI_RAG_CHAT_PROVIDER` | `null` (uses default) | Provider for chat in `ask()` |
| `AI_RAG_CHUNK_SIZE` | `2000` | Max characters per chunk |
| `AI_RAG_TOP_K` | `3` | Chunks to retrieve per query |
| `AI_RAG_TABLE` | `ai_documents` | Database table name |

---

## 🗺️ Roadmap

| Version | Feature | Status |
|---------|---------|--------|
| v1.0 | Ollama, OpenAI, Anthropic, DeepSeek | ✅ Released |
| v1.1 | Laravel 12 & 13 support | ✅ Released |
| v1.2 | Built-in RAG system (embed, ingest, search, ask) | ✅ Released |
| v2.0 | Function/Tool calling | 🔜 Planned |
| v2.1 | Groq driver | 🔜 Planned |
| v2.1 | Google Gemini driver | 🔜 Planned |
| v2.2 | Response caching | 🔜 Planned |
| v3.0 | Image generation | 🔜 Planned |

---

## ❤️ Support & Donations

If this package saves you time, consider supporting its development:

<p align="center">
  <a href="https://buymeacoffee.com/muradbdinfo">
    <img src="https://img.shields.io/badge/Buy%20Me%20A%20Coffee-FFDD00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black" alt="Buy Me A Coffee">
  </a>
  &nbsp;&nbsp;
  <a href="https://github.com/sponsors/muradbdinfo">
    <img src="https://img.shields.io/badge/GitHub%20Sponsors-EA4AAA?style=for-the-badge&logo=github-sponsors&logoColor=white" alt="GitHub Sponsors">
  </a>
</p>

**Other ways to support:**
- ⭐ Star this repo on GitHub
- 🐛 Report bugs or suggest features via [Issues](https://github.com/muradbdinfo/laravelai/issues)
- 🔀 Submit a pull request
- 📢 Share with your developer friends

---

## 👤 Credits

**Md Murad Hosen** — Full-Stack Laravel Vue Developer and DevOps Engineer from Chittagong, Bangladesh 🇧🇩

### Personal Links

| Platform | Link |
|----------|------|
| 🌐 Website | [easyit.com.bd](https://www.easyit.com.bd) |
| 📺 YouTube | [EasyBD IT](https://youtube.com/@easybdit) |
| 📘 Facebook | [Murad Hosen](https://facebook.com/muradhosenofficial) |
| 📱 WhatsApp | [+8801827517700](https://wa.me/8801827517700) |
| 💻 GitHub | [muradbdinfo](https://github.com/muradbdinfo) |

### Community Links

| Platform | Link |
|----------|------|
| 📘 Facebook Page | [EasyBD IT](https://www.facebook.com/easybdit) |
| 👥 Facebook Group | [EITBD](https://www.facebook.com/groups/eitbd) |
| 💬 WhatsApp Group | [Join Chat](https://chat.whatsapp.com/E3VV0K6lkrqEgXdngrt2Rk) |

---

## 📄 License

MIT License — see [LICENSE](LICENSE) for details.

Free to use in personal and commercial projects.

---

<p align="center">
  <sub>Made with ❤️ in Bangladesh 🇧🇩</sub><br>
  <sub>Built for the Laravel community worldwide</sub>
</p>