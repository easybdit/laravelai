<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AIChatController extends Controller
{
    private const PROVIDERS = [
        'ollama'    => ['label' => 'Ollama (Local)',     'icon' => '🖥️'],
        'openai'    => ['label' => 'OpenAI (ChatGPT)',   'icon' => '🟢'],
        'anthropic' => ['label' => 'Anthropic (Claude)', 'icon' => '🟠'],
        'deepseek'  => ['label' => 'DeepSeek',           'icon' => '🔵'],
    ];

    private function activeProvider(): string
    {
        $provider = session('ai_provider', config('ai.default', 'ollama'));
        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'ollama';
    }

    public function index(Request $request)
    {
        $sessions      = ChatSession::with('project')->latest()->get();
        $projects      = Project::withCount('files')->latest()->get();
        $activeSession = null;
        $messages      = collect();

        if ($request->has('session')) {
            $activeSession = ChatSession::with('messages', 'project')->find($request->session);
            $messages      = $activeSession?->messages ?? collect();
        }

        return view('laravelai::chat', [
            'sessions'       => $sessions,
            'activeSession'  => $activeSession,
            'messages'       => $messages,
            'providers'      => self::PROVIDERS,
            'activeProvider' => $this->activeProvider(),
            'projects'       => $projects,
        ]);
    }

    public function switchProvider(Request $request)
    {
        $request->validate(['provider' => 'required|in:ollama,openai,anthropic,deepseek']);
        session(['ai_provider' => $request->provider]);
        return response()->json(['provider' => $request->provider, 'ok' => true]);
    }

    public function newSession(Request $request)
    {
        $projectId = $request->input('project_id');
        $session   = ChatSession::create([
            'title'      => 'New Chat',
            'project_id' => $projectId ?: null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['session' => $session]);
        }

        return redirect()->route('ai-chat.index', ['session' => $session->id]);
    }

    public function deleteSession(ChatSession $session)
    {
        $session->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('ai-chat.index');
    }

    public function stream(Request $request)
    {
        $request->validate([
            'message'    => 'required|string|max:2000',
            'session_id' => 'required|integer|exists:chat_sessions,id',
        ]);

        $provider = $this->activeProvider();
        $session  = ChatSession::with('messages')->findOrFail($request->session_id);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => $request->message,
        ]);

        if ($session->messages->count() === 0 && $session->title === 'New Chat') {
            $session->update(['title' => str($request->message)->limit(50)]);
        }

        $history = $session->fresh()->load('messages')->toAIMessages();

        // RAG context injection — only if project has ingested documents
        if ($session->project_id) {
            $source   = 'project_' . $session->project_id;
            $docCount = DB::table(config('ai.rag.table', 'ai_documents'))
                          ->where('source', $source)
                          ->count();

            if ($docCount > 0) {
                try {
                    $context = AI::rag()->source($source)->search($request->message);

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

                        $systemPrompt = config('ai.rag.system_prompt',
                                            'Answer using ONLY the context below. If unsure, say so.')
                                        . "\n\nCONTEXT:\n" . $contextText;

                        foreach ($history as $i => $msg) {
                            if ($msg['role'] === 'user') {
                                $history[$i]['content'] = $systemPrompt . "\n\nQUESTION: " . $msg['content'];
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // RAG failure non-fatal
                }
            }
        }

        $isProjectSession = $session->project_id !== null;
        $chatModel        = config("ai.providers.{$provider}.model");

        return response()->stream(function () use ($session, $history, $provider, $isProjectSession, $chatModel) {
            $fullReply = '';

            // Clear any existing output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            try {
                AI::provider($provider)
                    ->model($chatModel)
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
                echo "data: " . json_encode(['error' => 'Connection error: ' . $e->getMessage()]) . "\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => 'Error: ' . $e->getMessage()]) . "\n\n";
            }

            if ($fullReply) {
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role'            => 'assistant',
                    'content'         => $fullReply,
                ]);
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
