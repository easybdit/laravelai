<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chat — LaravelAI</title>
    <link rel="stylesheet" href="{{ asset('vendor/laravelai/css/github-dark.min.css') }}">
    <script src="{{ asset('vendor/laravelai/js/marked.min.js') }}"></script>
    <script src="{{ asset('vendor/laravelai/js/highlight.min.js') }}"></script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --sidebar-bg:     #0f1117;
            --sidebar-border: #1e2433;
            --sidebar-text:   #8b95a8;
            --sidebar-hover:  #161c2d;
            --sidebar-active: #1a2035;
            --accent:         #6366f1;
            --accent-hover:   #4f46e5;
            --surface:        #ffffff;
            --bg:             #f5f6fa;
            --border:         #e8eaef;
            --text:           #1a1d27;
            --text-muted:     #8b95a8;
            --radius:         12px;
            --shadow:         0 1px 3px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.04);
            --project-color:  #10b981;
        }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: var(--bg); height: 100vh; display: flex; overflow: hidden; color: var(--text); }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

        /* ── SIDEBAR ── */
        .sidebar { width: 260px; min-width: 260px; background: var(--sidebar-bg); display: flex; flex-direction: column; height: 100vh; border-right: 1px solid var(--sidebar-border); }
        .sidebar-top { padding: 18px 14px 12px; }
        .app-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .brand-icon { width: 32px; height: 32px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .brand-name { font-size: 0.88rem; font-weight: 600; color: #e2e8f0; }
        .brand-sub  { font-size: 0.68rem; color: var(--sidebar-text); }
        .provider-select { width: 100%; background: #161c2d; border: 1px solid var(--sidebar-border); color: #c8d0de; padding: 8px 28px 8px 10px; border-radius: 8px; font-size: 0.82rem; font-family: inherit; cursor: pointer; margin-bottom: 8px; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
        .provider-select:focus { outline: none; border-color: var(--accent); }
        .provider-select option { background: #161c2d; }
        .provider-badge { font-size: 0.7rem; color: #3d4659; padding: 0 2px 10px; display: flex; align-items: center; gap: 6px; }
        .provider-dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
        .new-chat-btn { width: 100%; padding: 9px 14px; background: var(--accent); color: white; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; font-family: inherit; display: flex; align-items: center; gap: 8px; transition: background 0.15s; }
        .new-chat-btn:hover { background: var(--accent-hover); }

        /* ── SIDEBAR SECTIONS ── */
        .sidebar-body { flex: 1; overflow-y: auto; }
        .sidebar-section-label { padding: 12px 18px 5px; font-size: 0.67rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #2d3548; display: flex; align-items: center; justify-content: space-between; }
        .sidebar-section-label button { background: none; border: none; color: #3d4659; cursor: pointer; font-size: 0.75rem; padding: 2px 5px; border-radius: 4px; transition: all 0.15s; }
        .sidebar-section-label button:hover { background: #161c2d; color: #c8d0de; }

        /* ── PROJECT ITEMS ── */
        .project-item { display: flex; align-items: center; padding: 8px 10px; border-radius: 7px; cursor: pointer; font-size: 0.82rem; color: var(--sidebar-text); margin-bottom: 1px; gap: 8px; transition: background 0.1s, color 0.1s; margin: 0 8px 1px; }
        .project-item:hover  { background: var(--sidebar-hover); color: #c8d0de; }
        .project-item.active { background: #0d2018; color: #10b981; border-left: 2px solid var(--project-color); padding-left: 8px; }
        .project-icon { font-size: 0.8rem; flex-shrink: 0; }
        .project-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .project-badge { font-size: 0.6rem; background: #1a2a1f; color: var(--project-color); padding: 1px 5px; border-radius: 8px; flex-shrink: 0; }
        .project-actions { display: flex; gap: 3px; opacity: 0; transition: opacity 0.15s; }
        .project-item:hover .project-actions { opacity: 1; }
        .project-actions button { background: none; border: none; cursor: pointer; padding: 2px 4px; border-radius: 3px; font-size: 0.7rem; color: #3d4659; transition: all 0.15s; }
        .project-actions button:hover { background: #1a2433; color: #c8d0de; }
        .project-actions .del-btn:hover { background: #ef4444 !important; color: white !important; }

        /* ── SESSION ITEMS ── */
        .session-list { padding: 2px 8px; }
        .session-item { display: flex; align-items: center; padding: 8px 10px; border-radius: 7px; cursor: pointer; font-size: 0.82rem; color: var(--sidebar-text); text-decoration: none; margin-bottom: 1px; gap: 8px; transition: background 0.1s, color 0.1s; }
        .session-item:hover  { background: var(--sidebar-hover); color: #c8d0de; }
        .session-item.active { background: var(--sidebar-active); color: #e2e8f0; }
        .session-title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .session-project-tag { font-size: 0.6rem; color: var(--project-color); background: #0d2018; padding: 1px 4px; border-radius: 4px; flex-shrink: 0; }
        .delete-btn { background: none; border: none; color: transparent; cursor: pointer; padding: 2px 5px; border-radius: 4px; font-size: 0.7rem; flex-shrink: 0; transition: all 0.15s; }
        .session-item:hover .delete-btn { color: #3d4659; }
        .delete-btn:hover { background: #ef4444 !important; color: white !important; }

        .sidebar-footer { padding: 12px 14px; border-top: 1px solid var(--sidebar-border); font-size: 0.7rem; color: #2d3548; }
        .sidebar-footer a { color: #4a5568; text-decoration: none; }
        .sidebar-footer a:hover { color: var(--accent); }

        /* ── MAIN ── */
        .main { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .chat-header { padding: 13px 22px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow); z-index: 10; }
        .header-icon { width: 28px; height: 28px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .header-icon.project-header-icon { background: linear-gradient(135deg, #059669, #10b981); }
        .header-title { font-size: 0.9rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .header-project-tag { font-size: 0.7rem; color: var(--project-color); background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 10px; flex-shrink: 0; }
        .header-meta { margin-left: auto; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .header-provider { font-size: 0.72rem; color: var(--text-muted); background: var(--bg); border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; }
        .manage-files-btn { font-size: 0.75rem; color: var(--project-color); background: #ecfdf5; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 20px; cursor: pointer; transition: all 0.15s; }
        .manage-files-btn:hover { background: #d1fae5; }

        /* ── MESSAGES ── */
        .messages { flex: 1; overflow-y: auto; padding: 24px 0; }
        .msg-row { display: flex; gap: 12px; padding: 10px 24px; max-width: 900px; margin: 0 auto; width: 100%; }
        .avatar { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; }
        .avatar.user      { background: var(--accent); color: white; }
        .avatar.assistant { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; }
        .bubble-wrap { flex: 1; min-width: 0; }
        .bubble { padding: 12px 16px; border-radius: var(--radius); font-size: 0.88rem; line-height: 1.6; }
        .bubble.user      { background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow); color: var(--text); white-space: pre-wrap; }
        .bubble.assistant { background: transparent; color: var(--text); }
        .bubble-actions { display: flex; gap: 6px; margin-top: 4px; padding-left: 2px; }
        .copy-btn { font-size: 0.7rem; color: var(--text-muted); background: none; border: 1px solid var(--border); padding: 2px 8px; border-radius: 4px; cursor: pointer; transition: all 0.15s; }
        .copy-btn:hover { background: var(--bg); color: var(--text); }
        .md-body h1,.md-body h2,.md-body h3 { margin: 1em 0 0.4em; font-weight: 600; }
        .md-body p  { margin: 0.4em 0; }
        .md-body ul,.md-body ol { padding-left: 1.4em; margin: 0.4em 0; }
        .md-body pre { margin: 0.6em 0; border-radius: 8px; overflow: hidden; position: relative; }
        .md-body code:not(pre code) { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; font-size: 0.84em; }
        .md-body table { border-collapse: collapse; width: 100%; margin: 0.6em 0; }
        .md-body th,.md-body td { border: 1px solid var(--border); padding: 6px 10px; font-size: 0.84em; }
        .md-body th { background: var(--bg); }
        .code-block-wrap { position: relative; }
        .code-copy-btn { position: absolute; top: 7px; right: 8px; background: #374151; color: #9ca3af; border: none; padding: 3px 8px; border-radius: 4px; font-size: 0.68rem; cursor: pointer; transition: all 0.15s; }
        .code-copy-btn:hover { background: #4b5563; color: white; }
        .typing-cursor::after { content: '▋'; animation: blink 1s infinite; color: var(--accent); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

        /* ── INPUT ── */
        .input-area { padding: 16px 24px 20px; background: var(--surface); border-top: 1px solid var(--border); }
        .input-box { display: flex; gap: 10px; align-items: flex-end; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 12px; transition: border-color 0.15s, box-shadow 0.15s; }
        .input-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        textarea { flex: 1; background: none; border: none; outline: none; resize: none; font-size: 0.88rem; font-family: inherit; max-height: 160px; line-height: 1.55; color: var(--text); }
        textarea::placeholder { color: var(--text-muted); }
        .send-btn { background: var(--accent); color: white; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; transition: background 0.15s, transform 0.1s; }
        .send-btn:hover:not(:disabled) { background: var(--accent-hover); transform: scale(1.05); }
        .send-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
        .input-hint { display: flex; justify-content: space-between; margin-top: 7px; font-size: 0.69rem; color: var(--text-muted); padding: 0 2px; }
        .input-hint kbd { background: var(--bg); border: 1px solid var(--border); border-radius: 3px; padding: 1px 4px; font-family: 'Cascadia Code','Consolas',monospace; font-size: 0.67rem; }
        .no-session { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 14px; color: var(--text-muted); }
        .no-session-icon { width: 70px; height: 70px; background: linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.1)); border-radius: 22px; display: flex; align-items: center; justify-content: center; font-size: 30px; border: 1px solid rgba(99,102,241,0.15); }
        .no-session h2 { font-size: 1.05rem; font-weight: 600; color: #64748b; }
        .no-session p  { font-size: 0.84rem; }
        .rag-badge { font-size: 0.65rem; background: #ecfdf5; color: var(--project-color); border: 1px solid #a7f3d0; padding: 2px 6px; border-radius: 8px; margin-left: 6px; }

        /* ── MODAL ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: white; border-radius: 14px; width: 520px; max-width: 95vw; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal-header { padding: 18px 20px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 0.95rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: var(--text-muted); padding: 4px 8px; border-radius: 6px; }
        .modal-close:hover { background: var(--bg); color: var(--text); }
        .modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
        .btn { padding: 7px 16px; border-radius: 8px; font-size: 0.83rem; font-weight: 500; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e8eaef; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .form-input { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; font-family: inherit; outline: none; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        .form-label { font-size: 0.78rem; font-weight: 500; color: var(--text); margin-bottom: 5px; display: block; }
        .form-group { margin-bottom: 14px; }
        .file-upload-area { border: 2px dashed var(--border); border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.15s; color: var(--text-muted); font-size: 0.83rem; }
        .file-upload-area:hover { border-color: var(--accent); background: rgba(99,102,241,0.03); color: var(--text); }
        .file-upload-area input { display: none; }
        .file-list { margin-top: 12px; }
        .file-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: var(--bg); border-radius: 8px; font-size: 0.8rem; margin-bottom: 6px; }
        .file-item-icon { font-size: 1rem; flex-shrink: 0; }
        .file-item-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .file-item-status { font-size: 0.68rem; padding: 1px 6px; border-radius: 8px; flex-shrink: 0; }
        .status-ingested { background: #d1fae5; color: #059669; }
        .status-pending  { background: #fef3c7; color: #d97706; }
        .status-failed   { background: #fee2e2; color: #dc2626; }
        .file-item-del { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 0.8rem; padding: 2px 5px; border-radius: 4px; }
        .file-item-del:hover { background: #fee2e2; color: #dc2626; }
        .upload-progress { height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; margin-top: 8px; display: none; }
        .upload-progress-bar { height: 100%; background: var(--accent); width: 0%; transition: width 0.3s; }
        .welcome-caps { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .cap-chip { font-size: 0.75rem; background: var(--bg); border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; color: var(--text-muted); }
        .welcome-box { max-width: 520px; text-align: center; }
        .welcome-box h2 { font-size: 1.15rem; margin-bottom: 6px; color: var(--text); }
        .welcome-box p  { font-size: 0.85rem; color: var(--text-muted); }
    </style>
</head>
<body>

{{-- ── SIDEBAR ── --}}
<aside class="sidebar">
    <div class="sidebar-top">
        <div class="app-brand">
            <div class="brand-icon">🤖</div>
            <div>
                <div class="brand-name">LaravelAI Chat</div>
                <div class="brand-sub">muradbdinfo/laravelai</div>
            </div>
        </div>

        <select class="provider-select" id="providerSelect" onchange="switchProvider(this.value)">
            @foreach($providers as $key => $info)
                <option value="{{ $key }}" {{ $activeProvider === $key ? 'selected' : '' }}>
                    {{ $info['icon'] }} {{ $info['label'] }}
                </option>
            @endforeach
        </select>
        <div class="provider-badge">
            <span class="provider-dot"></span>
            {{ $providers[$activeProvider]['label'] }} active
        </div>

        <button class="new-chat-btn" onclick="createSession(null)">
            ＋ New Chat
        </button>
    </div>

    <div class="sidebar-body">

        {{-- ── PROJECTS ── --}}
        <div class="sidebar-section-label">
            📁 Projects
            <button onclick="openNewProjectModal()" title="New project">＋</button>
        </div>
        <div id="projectList">
            @foreach($projects as $proj)
            <div class="project-item {{ ($activeSession && $activeSession->project_id == $proj->id) ? 'active' : '' }}"
                 id="project-{{ $proj->id }}"
                 onclick="openProject({{ $proj->id }}, '{{ addslashes($proj->name) }}')">
                <span class="project-icon">📁</span>
                <span class="project-name">{{ $proj->name }}</span>
                <span class="project-badge">{{ $proj->files_count }} files</span>
                <div class="project-actions">
                    <button onclick="event.stopPropagation(); openFilesModal({{ $proj->id }}, '{{ addslashes($proj->name) }}')" title="Manage files">📎</button>
                    <button class="del-btn" onclick="event.stopPropagation(); deleteProject({{ $proj->id }})" title="Delete">✕</button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ── CHATS ── --}}
        <div class="sidebar-section-label" style="margin-top:8px;">💬 Chats</div>
        <div class="session-list">
            @foreach($sessions as $sess)
            <a href="{{ route('ai-chat.index', ['session' => $sess->id]) }}"
               class="session-item {{ ($activeSession && $activeSession->id == $sess->id) ? 'active' : '' }}">
                <span style="font-size:0.75rem;flex-shrink:0;">{{ $sess->project_id ? '📁' : '💬' }}</span>
                <span class="session-title">{{ $sess->title }}</span>
                @if($sess->project_id && $sess->project)
                    <span class="session-project-tag">{{ Str::limit($sess->project->name, 10) }}</span>
                @endif
                <button class="delete-btn" onclick="event.preventDefault();deleteSession({{ $sess->id }},this)">✕</button>
            </a>
            @endforeach
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="https://packagist.org/packages/muradbdinfo/laravelai" target="_blank">muradbdinfo/laravelai</a>
    </div>
</aside>

{{-- ── MAIN ── --}}
<main class="main">
    @if($activeSession)
        <div class="chat-header">
            <div class="header-icon {{ $activeSession->project_id ? 'project-header-icon' : '' }}">
                {{ $activeSession->project_id ? '📁' : '💬' }}
            </div>
            <span class="header-title">{{ $activeSession->title }}</span>
            @if($activeSession->project_id && $activeSession->project)
                <span class="header-project-tag">📁 {{ $activeSession->project->name }}</span>
                <span class="rag-badge">🧠 RAG ON</span>
            @endif
            <div class="header-meta">
                @if($activeSession->project_id)
                    <button class="manage-files-btn"
                        onclick="openFilesModal({{ $activeSession->project_id }}, '{{ addslashes($activeSession->project->name ?? '') }}')">
                        📎 Manage Files
                    </button>
                @endif
                <span class="header-provider">{{ $providers[$activeProvider]['icon'] }} {{ $providers[$activeProvider]['label'] }}</span>
            </div>
        </div>

        <div class="messages" id="messages">
            @if($messages->isEmpty())
                <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                    <div class="welcome-box">
                        @if($activeSession->project_id)
                            <div style="font-size:2.5rem;margin-bottom:10px;">📁</div>
                            <h2>{{ $activeSession->project->name ?? 'Project Chat' }}</h2>
                            <p>Ask anything — answers will use your uploaded project documents as context.</p>
                        @else
                            <div style="font-size:2.5rem;margin-bottom:10px;">🤖</div>
                            <h2>How can I help you?</h2>
                            <p>Responses render with full <strong>Markdown</strong> — headings, lists, code blocks, tables.</p>
                        @endif
                        <div class="welcome-caps" style="justify-content:center;margin-top:12px;">
                            <span class="cap-chip">📝 Markdown</span>
                            <span class="cap-chip">💻 Syntax highlight</span>
                            <span class="cap-chip">💾 DB history</span>
                            @if($activeSession->project_id)
                                <span class="cap-chip" style="background:#ecfdf5;border-color:#a7f3d0;color:#059669;">🧠 RAG context</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                @foreach($messages as $msg)
                <div class="msg-row {{ $msg->role }}">
                    <div class="avatar {{ $msg->role }}">
                        {{ $msg->role === 'user' ? 'You' : 'AI' }}
                    </div>
                    <div class="bubble-wrap">
                        <div class="bubble {{ $msg->role }}">
                            @if($msg->role === 'assistant')
                                <div class="md-body" data-raw="{{ e($msg->content) }}"></div>
                            @else
                                <div class="md-body">{{ $msg->content }}</div>
                            @endif
                        </div>
                        @if($msg->role === 'assistant')
                        <div class="bubble-actions">
                            <button class="copy-btn" onclick="copyText(this, {{ json_encode($msg->content) }})">Copy</button>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        <div class="input-area">
            <div class="input-box">
                <textarea id="input" rows="1"
                    placeholder="Message {{ $providers[$activeProvider]['label'] }}{{ $activeSession->project_id ? ' (RAG enabled)' : '' }}…"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send">▶</button>
            </div>
            <div class="input-hint">
                <span>
                    Markdown rendered · History saved
                    @if($activeSession->project_id)
                        · <span style="color:var(--project-color);">🧠 Project context active</span>
                    @endif
                </span>
                <span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> to send</span>
            </div>
        </div>
    @else
        <div class="no-session">
            <div class="no-session-icon">💬</div>
            <h2>Select a conversation</h2>
            <p>Choose from the sidebar or start a new chat.</p>
        </div>
    @endif
</main>

{{-- ── NEW PROJECT MODAL ── --}}
<div class="modal-overlay" id="newProjectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>📁 New Project</h3>
            <button class="modal-close" onclick="closeModal('newProjectModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Project Name *</label>
                <input type="text" id="newProjectName" class="form-input" placeholder="e.g. Product Docs, Legal KB…" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">Description (optional)</label>
                <input type="text" id="newProjectDesc" class="form-input" placeholder="What is this project about?" maxlength="500">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('newProjectModal')">Cancel</button>
            <button class="btn btn-primary" onclick="createProject()">Create Project</button>
        </div>
    </div>
</div>

{{-- ── FILE MANAGER MODAL ── --}}
<div class="modal-overlay" id="filesModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="filesModalTitle">📎 Project Files</h3>
            <button class="modal-close" onclick="closeModal('filesModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                <input type="file" id="fileInput" accept=".txt,.md,.pdf" onchange="uploadFile(this)">
                <div>📤 Click to upload file</div>
                <div style="font-size:0.75rem;margin-top:4px;color:#9ca3af;">Supports: .txt · .md · .pdf (max 10MB)</div>
            </div>
            <div class="upload-progress" id="uploadProgress">
                <div class="upload-progress-bar" id="uploadProgressBar"></div>
            </div>
            <div class="file-list" id="fileList">
                <div style="text-align:center;color:var(--text-muted);font-size:0.83rem;padding:20px 0;">Loading files…</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('filesModal')">Close</button>
            <button class="btn btn-primary" onclick="createProjectSession()" id="chatWithProjectBtn">💬 Chat with this project</button>
        </div>
    </div>
</div>

<script>
// ── MARKED SETUP ──
const renderer = new marked.Renderer();
renderer.code = function(code, lang) {
    const hl = (lang && hljs.getLanguage(lang))
        ? hljs.highlight(code, { language: lang }).value
        : hljs.highlightAuto(code).value;
    return `<div class="code-block-wrap"><pre><code class="hljs ${lang||''}">${hl}</code></pre><button class="code-copy-btn" onclick="copyCode(this)">Copy</button></div>`;
};
marked.setOptions({ renderer, breaks: true, gfm: true });

document.querySelectorAll('.md-body[data-raw]').forEach(el => {
    el.innerHTML = marked.parse(el.dataset.raw);
});

// ── STATE ──
const SESSION_ID   = {{ $activeSession ? $activeSession->id : 'null' }};
const PROJECT_ID   = {{ $activeSession && $activeSession->project_id ? $activeSession->project_id : 'null' }};
const CSRF         = document.querySelector('meta[name=csrf-token]').content;
let   isStreaming  = false;
let   currentProjectId = null;

// ── SCROLL ──
const msgEl = document.getElementById('messages');
if (msgEl) msgEl.scrollTop = msgEl.scrollHeight;
function scrollBottom() { if (msgEl) msgEl.scrollTop = msgEl.scrollHeight; }

// ── PROVIDER SWITCH ──
async function switchProvider(val) {
    await fetch('{{ route("ai-chat.provider") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ provider: val }),
    });
    location.reload();
}

// ── SESSION ──
async function createSession(projectId = null) {
    const body = { _token: CSRF };
    if (projectId) body.project_id = projectId;
    const r = await fetch('{{ route("ai-chat.sessions.new") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });
    const d = await r.json();
    window.location = '{{ route("ai-chat.index") }}?session=' + d.session.id;
}

async function deleteSession(id, btn) {
    if (!confirm('Delete this chat?')) return;
    await fetch(`{{ url('ai-chat/api/sessions') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    const item = btn.closest('.session-item');
    item.remove();
    if (SESSION_ID === id) window.location = '{{ route("ai-chat.index") }}';
}

// ── SEND MESSAGE ──
function sendMessage() {
    if (!SESSION_ID || isStreaming) return;
    const input = document.getElementById('input');
    const text  = input.value.trim();
    if (!text) return;

    input.value = '';
    input.style.height = 'auto';
    isStreaming = true;
    document.getElementById('sendBtn').disabled = true;

    appendMsg('user', text);

    const url = `{{ route('ai-chat.stream') }}?message=${encodeURIComponent(text)}&session_id=${SESSION_ID}`;
    const es  = new EventSource(url);
    const div = appendMsg('assistant', '');
    div.classList.add('typing-cursor');

    es.onmessage = e => {
        if (e.data === '[DONE]') {
            es.close();
            div.classList.remove('typing-cursor');
            isStreaming = false;
            document.getElementById('sendBtn').disabled = false;
            return;
        }
        const d = JSON.parse(e.data);
        if (d.error) {
            div.textContent = '⚠ ' + d.error;
            div.classList.remove('typing-cursor');
            es.close(); isStreaming = false;
            document.getElementById('sendBtn').disabled = false;
        } else if (d.text) {
            div.dataset.raw = (div.dataset.raw || '') + d.text;
            div.innerHTML = marked.parse(div.dataset.raw);
            scrollBottom();
        }
    };
    es.onerror = () => {
        es.close(); isStreaming = false;
        document.getElementById('sendBtn').disabled = false;
        div.classList.remove('typing-cursor');
    };
}

function appendMsg(role, text) {
    const wrap = document.createElement('div');
    wrap.className = 'msg-row ' + role;
    const av = document.createElement('div');
    av.className = 'avatar ' + role;
    av.textContent = role === 'user' ? 'You' : 'AI';
    const bwrap = document.createElement('div');
    bwrap.className = 'bubble-wrap';
    const bub = document.createElement('div');
    bub.className = 'bubble ' + role;
    const md = document.createElement('div');
    md.className = 'md-body';
    if (role === 'user') { md.textContent = text; }
    else { md.dataset.raw = text; md.innerHTML = text ? marked.parse(text) : ''; }
    bub.appendChild(md);
    bwrap.appendChild(bub);
    wrap.appendChild(av);
    wrap.appendChild(bwrap);
    msgEl.appendChild(wrap);
    scrollBottom();
    return md;
}

// ── INPUT EVENTS ──
@if($activeSession)
document.getElementById('input').addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); sendMessage(); }
});
document.getElementById('input').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 160) + 'px';
});
@endif

// ── COPY ──
function copyText(btn, text) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 1500);
    });
}
function copyCode(btn) {
    const code = btn.previousElementSibling.querySelector('code').innerText;
    navigator.clipboard.writeText(code).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 1500);
    });
}

// ── MODAL ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── PROJECTS ──
function openNewProjectModal() { openModal('newProjectModal'); }

async function createProject() {
    const name = document.getElementById('newProjectName').value.trim();
    if (!name) { alert('Project name required'); return; }
    const desc = document.getElementById('newProjectDesc').value.trim();
    const r = await fetch('{{ url("ai-chat/api/projects") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ name, description: desc }),
    });
    if (!r.ok) { alert('Failed to create project'); return; }
    closeModal('newProjectModal');
    location.reload();
}

async function openProject(projectId, projectName) {
    await createSession(projectId);
}

async function deleteProject(projectId) {
    if (!confirm('Delete this project and all its files and RAG data?')) return;
    await fetch(`{{ url("ai-chat/api/projects") }}/${projectId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    location.reload();
}

// ── FILE MANAGER ──
async function openFilesModal(projectId, projectName) {
    currentProjectId = projectId;
    document.getElementById('filesModalTitle').textContent = `📎 ${projectName} — Files`;
    document.getElementById('fileList').innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:0.83rem;padding:20px 0;">Loading…</div>';
    openModal('filesModal');
    await loadFiles(projectId);
}

async function loadFiles(projectId) {
    const r = await fetch(`{{ url("ai-chat/api/projects") }}/${projectId}/files`);
    const files = await r.json();
    const list = document.getElementById('fileList');
    if (!files.length) {
        list.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:0.83rem;padding:20px 0;">No files yet. Upload to enable RAG.</div>';
        return;
    }
    list.innerHTML = files.map(f => `
        <div class="file-item" id="file-${f.id}">
            <span class="file-item-icon">${f.mime_type === 'application/pdf' ? '📄' : '📝'}</span>
            <span class="file-item-name">${f.original_name}</span>
            <span class="file-item-status status-${f.status}">${f.status}</span>
            <button class="file-item-del" onclick="deleteFile(${projectId}, ${f.id})">✕</button>
        </div>
    `).join('');
}

async function uploadFile(input) {
    if (!input.files[0] || !currentProjectId) return;
    const file     = input.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', CSRF);

    const progress    = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    progress.style.display = 'block';
    progressBar.style.width = '30%';

    const r = await fetch(`{{ url("ai-chat/api/projects") }}/${currentProjectId}/files`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: formData,
    });

    progressBar.style.width = '100%';
    setTimeout(() => { progress.style.display = 'none'; progressBar.style.width = '0%'; }, 600);
    input.value = '';

    if (!r.ok) {
        const err = await r.json();
        alert(err.error || 'Upload failed');
        return;
    }
    await loadFiles(currentProjectId);
    // Reload sidebar to update file count
    setTimeout(() => location.reload(), 800);
}

async function deleteFile(projectId, fileId) {
    if (!confirm('Remove this file?')) return;
    await fetch(`{{ url("ai-chat/api/projects") }}/${projectId}/files/${fileId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    document.getElementById('file-' + fileId)?.remove();
}

async function createProjectSession() {
    if (!currentProjectId) return;
    closeModal('filesModal');
    await createSession(currentProjectId);
}
</script>
</body>
</html>
