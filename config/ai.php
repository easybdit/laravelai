<?php
return [
    'default' => env('AI_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'driver'     => 'ollama',
            'url'        => env('AI_OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model'      => env('AI_OLLAMA_MODEL', 'qwen2:1.5b'),
            'timeout'    => (int) env('AI_OLLAMA_TIMEOUT', 120),
            'keep_alive' => env('AI_OLLAMA_KEEP_ALIVE'),
            // Reasoning models (qwen3, etc.) "think" before answering by
            // default — often adding many seconds of latency for zero
            // visible benefit on simple questions. null = model's own
            // default; true/false explicitly turns it on/off for every
            // request against this provider (override per-call with
            // ->think()).
            'think'      => filter_var(env('AI_OLLAMA_THINK'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'options'    => ['temperature' => 0.7],
        ],
        'openai' => [
            'driver'  => 'openai',
            'api_key' => env('AI_OPENAI_KEY'),
            'url'     => env('AI_OPENAI_URL', 'https://api.openai.com/v1'),
            'model'   => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),
            'vision'  => true,
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ],
        'anthropic' => [
            'driver'  => 'anthropic',
            'api_key' => env('AI_ANTHROPIC_KEY'),
            'url'     => env('AI_ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'model'   => env('AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'version' => env('AI_ANTHROPIC_VERSION', '2023-06-01'),
            'timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),
            'vision'  => true,
            // Extended thinking — off unless explicitly enabled (adds cost
            // and latency by design). budget_tokens must stay below max_tokens;
            // the driver bumps max_tokens automatically when needed.
            'think'              => filter_var(env('AI_ANTHROPIC_THINK'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'think_budget_tokens' => (int) env('AI_ANTHROPIC_THINK_BUDGET', 10000),
            'options' => ['max_tokens' => 2000],
        ],
        'deepseek' => [
            'driver'  => 'deepseek',
            'api_key' => env('AI_DEEPSEEK_KEY'),
            'url'     => env('AI_DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
            'model'   => env('AI_DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('AI_DEEPSEEK_TIMEOUT', 60),
            'vision'  => false,
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ],
        'gemini' => [
            'driver'  => 'gemini',
            'api_key' => env('AI_GEMINI_KEY'),
            'url'     => env('AI_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model'   => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
            'timeout' => (int) env('AI_GEMINI_TIMEOUT', 60),
            'vision'  => true,
            // null = model's own default; true/false explicitly requests
            // (or suppresses) visible reasoning parts for 2.5-series models.
            'think'   => filter_var(env('AI_GEMINI_THINK'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ],
        'together' => [
            'driver'  => 'together',
            'api_key' => env('AI_TOGETHER_KEY'),
            'url'     => env('AI_TOGETHER_URL', 'https://api.together.xyz/v1'),
            'model'   => env('AI_TOGETHER_MODEL', 'meta-llama/Llama-3.3-70B-Instruct-Turbo'),
            'timeout' => (int) env('AI_TOGETHER_TIMEOUT', 60),
            'vision'  => false,
            'options' => ['temperature' => 0.7, 'max_tokens' => 2000],
            // Together AI also serves image generation (FLUX) — used by /image and /img
            // commands in the chat UI when 'image_enabled' is on.
            'image_enabled' => (bool) env('AI_TOGETHER_IMAGE_ENABLED', false),
            'image_model'   => env('AI_TOGETHER_IMAGE_MODEL', 'black-forest-labs/FLUX.1-schnell-Free'),
            'image_size'    => env('AI_TOGETHER_IMAGE_SIZE', '1024x1024'),
            'image_steps'   => (int) env('AI_TOGETHER_IMAGE_STEPS', 4),
        ],
    ],

    /**
     * Any OpenAI-compatible endpoint — LM Studio, vLLM, OpenRouter, a proxy,
     * a second in-house gateway. Add as many named entries as you like and
     * reach them with AI::custom('lmstudio') or, in the chat UI, the
     * "custom:lmstudio" provider key.
     */
    'custom_providers' => [
        // 'lmstudio' => [
        //     'label'   => 'LM Studio (local)',
        //     'url'     => 'http://127.0.0.1:1234/v1',
        //     'api_key' => null,
        //     'model'   => 'local-model',
        //     'timeout' => 60,
        //     'vision'  => false,
        // ],
    ],

    'rag' => [
        'embed_provider' => env('AI_RAG_PROVIDER', 'ollama'),
        'embed_model'    => env('AI_RAG_EMBED_MODEL', 'nomic-embed-text'),
        'chat_provider'  => env('AI_RAG_CHAT_PROVIDER', null),
        'chunk_size'     => (int) env('AI_RAG_CHUNK_SIZE', 2000),
        'top_k'          => (int) env('AI_RAG_TOP_K', 3),
        'table'          => env('AI_RAG_TABLE', 'ai_documents'),
        'system_prompt'  => env('AI_RAG_SYSTEM_PROMPT', 'Answer using ONLY the context below. If unsure, say so.'),

        /**
         * Auto-index arbitrary Eloquent models into RAG on save/delete — the
         * Laravel equivalent of the WordPress plugin's "Ask This Site". Empty
         * by default (opt-in): indexing nothing until you list a model here
         * avoids surprising anyone with unexpected embedding calls.
         *
         * 'posts' => [
         *     'model'  => \App\Models\Post::class,
         *     'source' => 'site_posts',
         *     'text'   => fn ($post) => $post->title . "\n\n" . strip_tags($post->body),
         *     'when'   => fn ($post) => $post->is_published,   // optional
         * ],
         */
        'auto_index' => [],
    ],

    /**
     * Chat UI — everything the WordPress plugin exposes through wp-admin
     * settings screens, expressed as config/env here instead. This package
     * ships no settings database table or admin panel by design: the host
     * app already has its own way to manage config (.env, a settings
     * package, feature flags) and duplicating that would fight it rather
     * than fit into it.
     */
    'chat' => [
        'system_prompt'      => env('AI_CHAT_SYSTEM_PROMPT', 'You are a helpful AI assistant.'),
        'lock_system_prompt' => (bool) env('AI_CHAT_LOCK_SYSTEM_PROMPT', false),
        'max_message_length' => (int) env('AI_CHAT_MAX_MESSAGE_LENGTH', 4000),
        'context_messages'   => (int) env('AI_CHAT_CONTEXT_MESSAGES', 10),
        'disable_storage'    => (bool) env('AI_CHAT_DISABLE_STORAGE', false),
        'show_internal_errors' => env('AI_CHAT_SHOW_INTERNAL_ERRORS'), // null = fall back to app.debug

        'access' => [
            'allow_guest' => (bool) env('AI_CHAT_ALLOW_GUEST', true),
            // everyone | auth | role | gate
            'restriction' => env('AI_CHAT_ACCESS_RESTRICTION', 'everyone'),
            'roles'       => array_filter(array_map('trim', explode(',', (string) env('AI_CHAT_ACCESS_ROLES', '')))),
            'role_method' => env('AI_CHAT_ACCESS_ROLE_METHOD', 'hasAnyRole'),
            'gate'        => env('AI_CHAT_ACCESS_GATE', 'use-ai-chat'),
        ],

        'rate_limit' => [
            'enabled' => (bool) env('AI_CHAT_RATE_LIMIT_ENABLED', true),
            'window'  => (int) env('AI_CHAT_RATE_LIMIT_WINDOW', 60),   // seconds
            'max'     => (int) env('AI_CHAT_RATE_LIMIT_MAX', 20),      // per identity, per window
            'ip_max'  => (int) env('AI_CHAT_RATE_LIMIT_IP_MAX', 60),   // secondary hard cap per IP
        ],

        'ip_blocklist' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_CHAT_IP_BLOCKLIST', ''))))),

        'word_filter' => [
            'enabled' => (bool) env('AI_CHAT_WORD_FILTER_ENABLED', false),
            'words'   => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_CHAT_WORD_FILTER_WORDS', ''))))),
            'action'  => env('AI_CHAT_WORD_FILTER_ACTION', 'block'), // block | warn
        ],

        'prompt_injection' => [
            'enabled' => (bool) env('AI_CHAT_PROMPT_INJECTION_DETECT', false),
            'action'  => env('AI_CHAT_PROMPT_INJECTION_ACTION', 'block'), // block | warn
        ],

        'captcha' => [
            'enabled' => (bool) env('AI_CHAT_CAPTCHA_ENABLED', false),
        ],

        'abuse_alert' => [
            'enabled' => (bool) env('AI_CHAT_ABUSE_ALERT_ENABLED', false),
            'email'   => env('AI_CHAT_ABUSE_ALERT_EMAIL'), // falls back to mail.from if empty
        ],

        'gdpr' => [
            'enabled' => (bool) env('AI_CHAT_GDPR_GATE_ENABLED', false),
            'text'    => env('AI_CHAT_GDPR_GATE_TEXT', 'This chat uses third-party AI services. By continuing, you agree to our Privacy Policy.'),
            'button'  => env('AI_CHAT_GDPR_GATE_BUTTON', 'I Accept & Continue'),
        ],

        'webhook' => [
            'url'    => env('AI_CHAT_WEBHOOK_URL'),
            'secret' => env('AI_CHAT_WEBHOOK_SECRET'),
        ],

        'attachments' => [
            'enabled'          => (bool) env('AI_CHAT_ATTACHMENTS_ENABLED', false),
            'max_image_mb'     => (int) env('AI_CHAT_ATTACHMENTS_MAX_IMAGE_MB', 5),
            'max_doc_mb'       => (int) env('AI_CHAT_ATTACHMENTS_MAX_DOC_MB', 10),
            'allowed_images'   => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
            'allowed_docs'     => ['txt', 'md', 'pdf'],
        ],

        /**
         * Presentation — welcome message, suggested prompts, avatar, colors,
         * floating widget defaults. Read by the chat view and the
         * <x-laravelai::widget /> Blade component.
         */
        'ui' => [
            'title'                => env('AI_CHAT_TITLE', 'AI Chat'),
            'placeholder'          => env('AI_CHAT_PLACEHOLDER', 'Ask me anything...'),
            'welcome_enabled'      => (bool) env('AI_CHAT_WELCOME_ENABLED', false),
            'welcome_message'      => env('AI_CHAT_WELCOME_MESSAGE', 'Hello! How can I help you today?'),
            'suggested_questions'  => array_values(array_filter(array_map('trim', explode('|', (string) env('AI_CHAT_SUGGESTED_QUESTIONS', ''))))),
            'avatar_url'           => env('AI_CHAT_AVATAR_URL'),
            'color_accent'         => env('AI_CHAT_COLOR_ACCENT', '#6366f1'),
            'color_user_bg'        => env('AI_CHAT_COLOR_USER_BG', '#1a56db'),
            'color_bot_bg'         => env('AI_CHAT_COLOR_BOT_BG', '#f3f4f6'),
            'voice_input_enabled'  => (bool) env('AI_CHAT_VOICE_INPUT_ENABLED', true),
            'tts_enabled'          => (bool) env('AI_CHAT_TTS_ENABLED', true),
            'export_enabled'       => (bool) env('AI_CHAT_EXPORT_ENABLED', true),
        ],

        'floating_widget' => [
            'enabled'  => (bool) env('AI_CHAT_WIDGET_ENABLED', false),
            'position' => env('AI_CHAT_WIDGET_POSITION', 'bottom-right'), // bottom-right | bottom-left
            'label'    => env('AI_CHAT_WIDGET_LABEL', 'Chat with us'),
            'profile'  => env('AI_CHAT_WIDGET_PROFILE'), // optional bot_profiles key
        ],

        /**
         * Named presets — provider, title, system prompt, attachments — that
         * an embed can load instead of the global defaults above:
         *   <x-laravelai::widget profile="support" />
         *
         * 'support' => [
         *     'label'          => 'Support Bot',
         *     'provider'       => 'openai',
         *     'title'          => 'Support',
         *     'system_prompt'  => 'You are a friendly support agent for Acme Inc.',
         *     'attachments'    => true,
         * ],
         */
        'bot_profiles' => [],
    ],

    'logging' => [
        'enabled' => (bool) env('AI_LOG_ENABLED', false),
        'channel' => env('AI_LOG_CHANNEL', 'stack'),
    ],

    'retry' => [
        'times' => (int) env('AI_RETRY_TIMES', 2),
        'sleep' => (int) env('AI_RETRY_SLEEP', 1000),
    ],
];
