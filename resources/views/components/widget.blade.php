@props([
    'profile'  => config('ai.chat.floating_widget.profile'),
    'position' => config('ai.chat.floating_widget.position', 'bottom-right'),
    'label'    => config('ai.chat.floating_widget.label', 'Chat with us'),
])
@php
    $bp = ($profile && ($p = config("ai.chat.bot_profiles.{$profile}"))) ? $p : [];
    $title = $bp['label'] ?? config('ai.chat.ui.title', 'AI Chat');
    $accent = config('ai.chat.ui.color_accent', '#6366f1');
    $avatar = config('ai.chat.ui.avatar_url');
    $widgetId = 'laravelai-widget-' . uniqid();
@endphp
<div id="{{ $widgetId }}" class="laravelai-widget laravelai-widget--{{ $position }}" {{ $attributes }}>
    <style>
        #{{ $widgetId }} { --lw-accent: {{ $accent }}; position: fixed; z-index: 2147483000; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        #{{ $widgetId }}.laravelai-widget--bottom-right { right: 20px; bottom: 20px; }
        #{{ $widgetId }}.laravelai-widget--bottom-left  { left: 20px; bottom: 20px; }
        #{{ $widgetId }} * { box-sizing: border-box; }
        #{{ $widgetId }} .lw-launcher { display: flex; align-items: center; gap: 8px; background: var(--lw-accent); color: #fff; border: none; padding: 12px 18px; border-radius: 999px; box-shadow: 0 6px 20px rgba(0,0,0,.18); cursor: pointer; font-size: 0.87rem; font-weight: 600; }
        #{{ $widgetId }} .lw-launcher:hover { filter: brightness(1.06); }
        #{{ $widgetId }} .lw-panel { display: none; flex-direction: column; position: absolute; bottom: 64px; width: 360px; max-width: calc(100vw - 40px); height: 520px; max-height: 70vh; background: #fff; border-radius: 16px; box-shadow: 0 16px 48px rgba(0,0,0,.22); overflow: hidden; }
        #{{ $widgetId }}.laravelai-widget--bottom-right .lw-panel { right: 0; }
        #{{ $widgetId }}.laravelai-widget--bottom-left  .lw-panel { left: 0; }
        #{{ $widgetId }}.lw-open .lw-panel { display: flex; }
        #{{ $widgetId }} .lw-head { background: var(--lw-accent); color: #fff; padding: 12px 14px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; }
        #{{ $widgetId }} .lw-head img { width: 22px; height: 22px; border-radius: 6px; object-fit: cover; }
        #{{ $widgetId }} .lw-close { margin-left: auto; background: rgba(255,255,255,.18); border: none; color: #fff; width: 24px; height: 24px; border-radius: 6px; cursor: pointer; }
        #{{ $widgetId }} .lw-body { flex: 1; overflow-y: auto; padding: 12px; background: #f7f7fb; display: flex; flex-direction: column; gap: 10px; }
        #{{ $widgetId }} .lw-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: 0.83rem; line-height: 1.5; }
        #{{ $widgetId }} .lw-msg.user { align-self: flex-end; background: var(--lw-accent); color: #fff; border-bottom-right-radius: 3px; white-space: pre-wrap; }
        #{{ $widgetId }} .lw-msg.assistant { align-self: flex-start; background: #fff; border: 1px solid #e8eaef; border-bottom-left-radius: 3px; color: #1a1d27; }
        #{{ $widgetId }} .lw-msg.assistant p { margin: 0.3em 0; }
        #{{ $widgetId }} .lw-foot { display: flex; gap: 6px; padding: 10px; border-top: 1px solid #e8eaef; background: #fff; }
        #{{ $widgetId }} .lw-foot textarea { flex: 1; resize: none; border: 1px solid #e8eaef; border-radius: 10px; padding: 8px 10px; font-size: 0.83rem; font-family: inherit; outline: none; max-height: 90px; }
        #{{ $widgetId }} .lw-foot textarea:focus { border-color: var(--lw-accent); }
        #{{ $widgetId }} .lw-send { background: var(--lw-accent); color: #fff; border: none; width: 34px; height: 34px; border-radius: 9px; cursor: pointer; flex-shrink: 0; }
        #{{ $widgetId }} .lw-send:disabled { opacity: .4; cursor: not-allowed; }
    </style>

    <button class="lw-launcher" onclick="document.getElementById('{{ $widgetId }}').classList.toggle('lw-open')">
        💬 {{ $label }}
    </button>

    <div class="lw-panel">
        <div class="lw-head">
            @if($avatar)<img src="{{ $avatar }}" alt="">@else 🤖 @endif
            <span>{{ $title }}</span>
            <button class="lw-close" onclick="document.getElementById('{{ $widgetId }}').classList.remove('lw-open')">✕</button>
        </div>
        <div class="lw-body" id="{{ $widgetId }}-body"></div>
        <div class="lw-foot">
            <textarea id="{{ $widgetId }}-input" rows="1" placeholder="{{ config('ai.chat.ui.placeholder', 'Ask me anything...') }}"></textarea>
            <button class="lw-send" id="{{ $widgetId }}-send">▶</button>
        </div>
    </div>
</div>

<script src="{{ asset('vendor/laravelai/js/marked.min.js') }}"></script>
<script>
(function () {
    const root      = document.getElementById('{{ $widgetId }}');
    const body      = document.getElementById('{{ $widgetId }}-body');
    const input     = document.getElementById('{{ $widgetId }}-input');
    const sendBtn   = document.getElementById('{{ $widgetId }}-send');
    const CSRF      = {!! json_encode(csrf_token()) !!};
    const PROFILE   = {!! json_encode($profile) !!};
    let sessionId   = null;
    let streaming   = false;

    function addMsg(role, text) {
        const div = document.createElement('div');
        div.className = 'lw-msg ' + role;
        div.innerHTML = (role === 'assistant' && window.marked) ? marked.parse(text) : text;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
        return div;
    }

    async function ensureSession() {
        if (sessionId) return sessionId;
        const body = { _token: CSRF };
        if (PROFILE) body.profile = PROFILE;
        const r = await fetch('{{ route("ai-chat.sessions.new") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const d = await r.json();
        sessionId = d.session.id;
        return sessionId;
    }

    async function send() {
        const text = input.value.trim();
        if (!text || streaming) return;
        streaming = true;
        sendBtn.disabled = true;
        input.value = '';
        addMsg('user', text);
        const replyEl = addMsg('assistant', '');
        let raw = '';

        try {
            const sid = await ensureSession();
            const response = await fetch('{{ route("ai-chat.stream") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'text/event-stream' },
                body: JSON.stringify({ message: text, session_id: sid }),
            });

            if (!response.ok) {
                let msg = 'Something went wrong.';
                try { msg = (await response.json()).error || msg; } catch (e) {}
                replyEl.textContent = '⚠ ' + msg;
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let done = false;

            while (!done) {
                const { value, done: streamDone } = await reader.read();
                done = streamDone;
                buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
                const events = buffer.split('\n\n');
                buffer = events.pop();
                for (const evt of events) {
                    const line = evt.replace(/^data:\s?/, '').trim();
                    if (!line || line === '[DONE]') { if (line === '[DONE]') done = true; continue; }
                    let d;
                    try { d = JSON.parse(line); } catch (e) { continue; }
                    if (d.text) {
                        raw += d.text;
                        replyEl.innerHTML = window.marked ? marked.parse(raw) : raw;
                        body.scrollTop = body.scrollHeight;
                    } else if (d.error) {
                        replyEl.textContent = '⚠ ' + d.error;
                    }
                }
            }
        } catch (e) {
            replyEl.textContent = '⚠ Connection error.';
        } finally {
            streaming = false;
            sendBtn.disabled = false;
        }
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 90) + 'px';
    });
})();
</script>
