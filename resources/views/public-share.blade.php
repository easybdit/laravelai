<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Shared-but-unlisted, not shared-and-searchable — a link someone chose to hand out deliberately shouldn't also end up in Google. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $session->title ?: 'Shared Conversation' }} — AI Chat</title>
    <link rel="stylesheet" href="{{ asset('vendor/laravelai/css/github-dark.min.css') }}">
    <script src="{{ asset('vendor/laravelai/js/marked.min.js') }}"></script>
    <script src="{{ asset('vendor/laravelai/js/highlight.min.js') }}"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f5f6fa; color: #1a1d27; padding: 32px 16px 60px; }
        .wrap { max-width: 720px; margin: 0 auto; }
        .banner { background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; border-radius: 10px; padding: 10px 16px; font-size: 0.82rem; margin-bottom: 20px; text-align: center; }
        h1 { font-size: 1.15rem; margin: 0 0 4px; }
        .sub { color: #6b7280; font-size: 0.82rem; margin-bottom: 24px; }
        .msg-row { margin-bottom: 18px; }
        .msg-label { font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; }
        .msg-label.user { color: #1a56db; }
        .msg-label.assistant { color: #4f46e5; }
        .bubble { border-radius: 10px; padding: 12px 16px; font-size: 0.9rem; line-height: 1.6; }
        .bubble.user { background: #eef2ff; }
        .bubble.assistant { background: #fff; border: 1px solid #e8eaef; }
        .bubble p:first-child { margin-top: 0; }
        .bubble p:last-child { margin-bottom: 0; }
        .bubble pre { background: #1e2433; color: #e2e8f0; padding: 10px 12px; border-radius: 8px; overflow-x: auto; font-size: 0.82rem; }
        .bubble code { font-family: 'Cascadia Code', Consolas, monospace; }
        .bubble pre code { background: none; padding: 0; }
        .bubble :not(pre) > code { background: rgba(0,0,0,.06); padding: 1px 5px; border-radius: 4px; }
        footer { text-align: center; color: #9ca3af; font-size: 0.76rem; margin-top: 32px; }
        footer a { color: #6366f1; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="banner">🔗 This is a shared, read-only view of a conversation — you're not signed in here, and can't reply.</div>
    <h1>{{ $session->title ?: 'Shared Conversation' }}</h1>
    <p class="sub">{{ count($messages) }} message{{ count($messages) === 1 ? '' : 's' }}</p>

    @foreach($messages as $msg)
        <div class="msg-row">
            <div class="msg-label {{ $msg->role }}">{{ $msg->role === 'user' ? 'User' : 'Assistant' }}</div>
            <div class="bubble {{ $msg->role }} md-body" data-raw="{{ e($msg->content) }}"></div>
        </div>
    @endforeach

    <footer>Shared via <a href="{{ route('ai-chat.index') }}">LaravelAI Chat</a></footer>
</div>

<script>
const renderer = new marked.Renderer();
renderer.code = function (code, lang) {
    const hl = (lang && hljs.getLanguage(lang)) ? hljs.highlight(code, { language: lang }).value : hljs.highlightAuto(code).value;
    return `<pre><code class="hljs ${lang || ''}">${hl}</code></pre>`;
};
marked.setOptions({ renderer, breaks: true, gfm: true });
document.querySelectorAll('.md-body[data-raw]').forEach(el => {
    el.innerHTML = marked.parse(el.dataset.raw);
});
</script>
</body>
</html>
