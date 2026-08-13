<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $ui['title'] ?? 'AI Chat' }} — LaravelAI</title>
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
            --accent:         {{ $ui['color_accent'] ?? '#6366f1' }};
            --accent-hover:   {{ $ui['color_accent'] ?? '#4f46e5' }};
            --surface:        #ffffff;
            --bg:             #f5f6fa;
            --border:         #e8eaef;
            --text:           #1a1d27;
            --text-muted:     #8b95a8;
            --radius:         12px;
            --shadow:         0 1px 3px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.04);
            --project-color:  #10b981;
            --user-bg:        {{ $ui['color_user_bg'] ?? '#1a56db' }};
            --bot-bg:         {{ $ui['color_bot_bg'] ?? '#f3f4f6' }};
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
        .search-box { position: relative; margin-bottom: 4px; }
        .search-box input { width: 100%; background: #161c2d; border: 1px solid var(--sidebar-border); color: #c8d0de; padding: 7px 10px 7px 28px; border-radius: 8px; font-size: 0.8rem; font-family: inherit; outline: none; }
        .search-box input:focus { border-color: var(--accent); }
        .search-box::before { content: '🔍'; position: absolute; left: 9px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; opacity: 0.5; }
        .session-item.search-hidden { display: none; }

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

        .sidebar-footer { padding: 12px 14px; border-top: 1px solid var(--sidebar-border); font-size: 0.7rem; color: #2d3548; display: flex; justify-content: space-between; align-items: center; }
        .sidebar-footer a { color: #4a5568; text-decoration: none; }
        .sidebar-footer a:hover { color: var(--accent); }

        /* ── MAIN ── */
        .main { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        body.chat-fullscreen .sidebar { display: none; }
        body.chat-fullscreen .main { position: fixed; inset: 0; z-index: 999; }
        .chat-header { padding: 13px 22px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow); z-index: 10; }
        .header-icon { width: 28px; height: 28px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; overflow: hidden; }
        .header-icon img { width: 100%; height: 100%; object-fit: cover; }
        .header-icon.project-header-icon { background: linear-gradient(135deg, #059669, #10b981); }
        .header-title { font-size: 0.9rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .header-project-tag { font-size: 0.7rem; color: var(--project-color); background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 10px; flex-shrink: 0; }
        .header-meta { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .header-provider { font-size: 0.72rem; color: var(--text-muted); background: var(--bg); border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; }
        .manage-files-btn { font-size: 0.75rem; color: var(--project-color); background: #ecfdf5; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 20px; cursor: pointer; transition: all 0.15s; }
        .manage-files-btn:hover { background: #d1fae5; }
        .icon-btn { background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); width: 30px; height: 30px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; transition: all 0.15s; flex-shrink: 0; }
        .icon-btn:hover { background: #e8eaef; color: var(--text); }
        .icon-btn.active { background: var(--accent-soft, #eeecfb); color: var(--accent); border-color: var(--accent); }

        /* ── MESSAGES ── */
        .messages { flex: 1; overflow-y: auto; padding: 24px 0; }
        .msg-row { display: flex; gap: 12px; padding: 10px 24px; max-width: 900px; margin: 0 auto; width: 100%; }
        .avatar { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; overflow: hidden; }
        .avatar.user      { background: var(--user-bg); color: white; }
        .avatar.assistant { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .bubble-wrap { flex: 1; min-width: 0; }
        .msg-meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
        .msg-label { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
        .msg-time { font-size: 0.68rem; color: var(--text-muted); opacity: 0.75; font-variant-numeric: tabular-nums; }
        .bubble { padding: 12px 16px; border-radius: var(--radius); font-size: 0.88rem; line-height: 1.6; }
        .bubble.user      { background: var(--bot-bg); border: 1px solid var(--border); box-shadow: var(--shadow); color: var(--text); white-space: pre-wrap; }
        .bubble.assistant { background: transparent; color: var(--text); }
        .bubble-actions { display: flex; gap: 4px; margin-top: 5px; padding-left: 2px; flex-wrap: wrap; align-items: center; }
        .icon-action { font-size: 0.72rem; color: var(--text-muted); background: none; border: 1px solid var(--border); padding: 3px 8px; border-radius: 6px; cursor: pointer; transition: all 0.15s; line-height: 1.2; }
        .icon-action:hover { background: var(--bg); color: var(--text); }
        .icon-action.rated { background: var(--accent-soft, #eeecfb); color: var(--accent); border-color: var(--accent); }
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
        .regen-row { max-width: 900px; margin: 4px auto 0; padding: 0 24px 6px; }
        .regen-btn { font-size: 0.76rem; color: var(--text-muted); background: var(--surface); border: 1px solid var(--border); padding: 5px 12px; border-radius: 8px; cursor: pointer; }
        .regen-btn:hover { background: var(--bg); color: var(--text); }

        /* ── THINKING (reasoning-model live progress) ── */
        .think-block { margin: 0 0 8px; }
        .think-block summary { cursor: pointer; font-size: 0.76rem; color: var(--text-muted); list-style: none; display: inline-flex; align-items: center; gap: 5px; padding: 3px 0; user-select: none; }
        .think-block summary::-webkit-details-marker { display: none; }
        .think-block summary::before { content: '▸'; font-size: 0.65rem; transition: transform 0.15s; display: inline-block; }
        .think-block[open] summary::before { transform: rotate(90deg); }
        .think-block.live summary { animation: pulse 1.6s infinite; }
        .think-block .think-timer { font-variant-numeric: tabular-nums; }
        .think-block .think-body { font-size: 0.78rem; font-style: italic; color: var(--text-muted); background: var(--bg); border-left: 2px solid var(--border); padding: 8px 10px; margin-top: 4px; border-radius: 0 8px 8px 0; max-height: 160px; overflow-y: auto; white-space: pre-wrap; }

        /* ── CAPTCHA / GDPR ── */
        .gdpr-overlay { position: absolute; inset: 0; background: rgba(20,18,28,0.55); display: none; align-items: flex-end; justify-content: center; z-index: 50; }
        .gdpr-overlay.open { display: flex; }
        .gdpr-card { background: var(--surface); border-radius: 14px 14px 0 0; max-width: 640px; width: 100%; padding: 18px 22px; box-shadow: 0 -8px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .gdpr-card p { flex: 1; min-width: 220px; font-size: 0.83rem; color: var(--text-muted); }
        .captcha-row { display: flex; align-items: center; gap: 8px; padding: 0 24px 8px; max-width: 900px; margin: 0 auto; width: 100%; }
        .captcha-row.hidden { display: none; }
        .captcha-row span { font-size: 0.8rem; color: var(--text-muted); }
        .captcha-row input { width: 60px; padding: 5px 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.82rem; font-family: inherit; }

        /* ── ATTACHMENTS (chat input) ── */
        .attach-chips { display: flex; gap: 6px; flex-wrap: wrap; padding: 0 2px 8px; }
        .attach-chip { display: flex; align-items: center; gap: 5px; font-size: 0.74rem; background: var(--bg); border: 1px solid var(--border); padding: 3px 6px 3px 9px; border-radius: 20px; color: var(--text-muted); }
        .attach-chip button { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 0.7rem; padding: 2px; }
        .attach-chip button:hover { color: #dc2626; }

        /* ── INPUT ── */
        .input-area { padding: 10px 24px 20px; background: var(--surface); border-top: 1px solid var(--border); position: relative; }
        .input-box { display: flex; gap: 8px; align-items: flex-end; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 12px; transition: border-color 0.15s, box-shadow 0.15s; }
        .input-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        textarea { flex: 1; background: none; border: none; outline: none; resize: none; font-size: 0.88rem; font-family: inherit; max-height: 160px; line-height: 1.55; color: var(--text); }
        textarea::placeholder { color: var(--text-muted); }
        .input-icon-btn { background: none; border: none; color: var(--text-muted); width: 30px; height: 36px; cursor: pointer; font-size: 0.95rem; flex-shrink: 0; transition: color 0.15s; }
        .input-icon-btn:hover { color: var(--text); }
        .input-icon-btn.recording { color: #dc2626; animation: pulse 1s infinite; }
        .send-btn { background: var(--accent); color: white; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; transition: background 0.15s, transform 0.1s; }
        .send-btn:hover:not(:disabled) { background: var(--accent-hover); transform: scale(1.05); }
        .send-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
        .send-btn.stop-btn { background: #dc2626; opacity: 1; cursor: pointer; }
        .send-btn.stop-btn:hover { background: #b91c1c; }
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
        .cap-chip.clickable { cursor: pointer; transition: all 0.15s; }
        .cap-chip.clickable:hover { background: var(--accent-soft, #eeecfb); color: var(--accent); border-color: var(--accent); }
        .welcome-box { max-width: 520px; text-align: center; }
        .welcome-box h2 { font-size: 1.15rem; margin-bottom: 6px; color: var(--text); }
        .welcome-box p  { font-size: 0.85rem; color: var(--text-muted); }
        .welcome-custom-msg { background: var(--bot-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 16px; font-size: 0.87rem; text-align: left; margin-top: 14px; }
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
        @if($sessions->count() > 3)
        <div class="search-box" style="margin:0 8px 6px;">
            <input type="text" id="sessionSearch" placeholder="Search chats…" oninput="filterSessions(this.value)">
        </div>
        @endif
        <div class="session-list" id="sessionList">
            @foreach($sessions as $sess)
            <a href="{{ route('ai-chat.index', ['session' => $sess->id]) }}"
               class="session-item {{ ($activeSession && $activeSession->id == $sess->id) ? 'active' : '' }}"
               data-title="{{ strtolower($sess->title) }}" id="session-link-{{ $sess->id }}">
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
        <a href="{{ route('ai-chat.analytics') }}" title="Analytics">📊</a>
    </div>
</aside>

{{-- ── MAIN ── --}}
<main class="main">
    @if($activeSession)
        <div class="chat-header">
            <div class="header-icon {{ $activeSession->project_id ? 'project-header-icon' : '' }}">
                @if(!$activeSession->project_id && !empty($ui['avatar_url']))
                    <img src="{{ $ui['avatar_url'] }}" alt="">
                @else
                    {{ $activeSession->project_id ? '📁' : '💬' }}
                @endif
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
                <span class="header-provider" id="headerProvider">{{ $providers[$activeProvider]['icon'] }} {{ $providers[$activeProvider]['label'] }}</span>
                @if($ui['export_enabled'] ?? true)
                <button class="icon-btn" title="Export conversation" onclick="exportConversation()">⬇</button>
                @endif
                <button class="icon-btn" id="fullscreenBtn" title="Fullscreen" onclick="toggleFullscreen()">⤢</button>
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
                        @if(!empty($ui['welcome_enabled']) && !empty($ui['welcome_message']))
                            <div class="welcome-custom-msg" id="welcomeMsg" data-raw="{{ e($ui['welcome_message']) }}"></div>
                        @endif
                        @if(!empty($ui['suggested_questions']))
                            <div class="welcome-caps" style="justify-content:center;margin-top:12px;">
                                @foreach($ui['suggested_questions'] as $q)
                                    <span class="cap-chip clickable" onclick="sendSuggested({{ json_encode($q) }})">{{ $q }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                @foreach($messages as $msg)
                <div class="msg-row {{ $msg->role }}">
                    <div class="avatar {{ $msg->role }}">
                        @if($msg->role === 'assistant' && !empty($ui['avatar_url']))
                            <img src="{{ $ui['avatar_url'] }}" alt="">
                        @else
                            {{ $msg->role === 'user' ? 'You' : 'AI' }}
                        @endif
                    </div>
                    <div class="bubble-wrap" data-msg-id="{{ $msg->id }}" data-role="{{ $msg->role }}">
                        <div class="msg-meta">
                            <span class="msg-label">{{ $msg->role === 'user' ? 'You' : 'Assistant' }}</span>
                            <span class="msg-time">{{ $msg->created_at?->format('H:i') }}</span>
                        </div>
                        <div class="bubble {{ $msg->role }}">
                            @if($msg->role === 'assistant')
                                <div class="md-body" data-raw="{{ e($msg->content) }}"></div>
                            @else
                                <div class="md-body">{{ $msg->content }}</div>
                            @endif
                        </div>
                        @if($msg->role === 'assistant')
                        <div class="bubble-actions">
                            <button class="icon-action" onclick="copyText(this, {{ json_encode($msg->content) }})">Copy</button>
                            <button class="icon-action" onclick="downloadText({{ json_encode($msg->content) }}, 'message-{{ $msg->id }}.txt')">⬇ Save</button>
                            @if($ui['tts_enabled'] ?? true)
                            <button class="icon-action" onclick="toggleReadAloud(this, {{ json_encode($msg->content) }})">🔊 Read</button>
                            @endif
                            <button class="icon-action feedback-up"   onclick="sendFeedback({{ $msg->id }}, 1, this)">👍</button>
                            <button class="icon-action feedback-down" onclick="sendFeedback({{ $msg->id }}, -1, this)">👎</button>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        @if($captchaEnabled ?? false)
        <div class="captcha-row hidden" id="captchaRow">
            <span id="captchaQuestion">…</span>
            <input type="number" id="captchaAnswer" placeholder="?">
        </div>
        @endif

        @if($attachmentsEnabled ?? false)
        <div class="attach-chips" id="attachChips"></div>
        @endif

        <div class="input-area">
            <div class="gdpr-overlay" id="gdprOverlay">
                <div class="gdpr-card">
                    <p>{{ $gdpr['text'] ?? '' }}</p>
                    <button class="btn btn-primary" onclick="acceptGdpr()">{{ $gdpr['button'] ?? 'I Accept & Continue' }}</button>
                </div>
            </div>
            <div class="input-box">
                @if($attachmentsEnabled ?? false)
                <button class="input-icon-btn" title="Attach file" onclick="document.getElementById('attachInput').click()">📎</button>
                <input type="file" id="attachInput" accept=".png,.jpg,.jpeg,.webp,.gif,.txt,.md,.pdf" style="display:none" onchange="uploadAttachment(this)">
                @endif
                <textarea id="input" rows="1"
                    placeholder="Message {{ $providers[$activeProvider]['label'] }}{{ $activeSession->project_id ? ' (RAG enabled)' : '' }}…"></textarea>
                @if($ui['voice_input_enabled'] ?? true)
                <button class="input-icon-btn" id="micBtn" title="Voice input" onclick="toggleVoiceInput()">🎤</button>
                @endif
                <button class="send-btn" id="sendBtn" onclick="sendOrStop()" title="Send">▶</button>
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
let   HAS_MESSAGES = {{ $messages->isNotEmpty() ? 'true' : 'false' }};
const CAPTCHA_ENABLED = {{ ($captchaEnabled ?? false) ? 'true' : 'false' }};
const ATTACHMENTS_ENABLED = {{ ($attachmentsEnabled ?? false) ? 'true' : 'false' }};
let   isStreaming  = false;
let   currentProjectId = null;
let   captchaToken = null;
let   currentAbortController = null;
let   pendingAttachments = [];

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

function filterSessions(term) {
    term = term.trim().toLowerCase();
    document.querySelectorAll('#sessionList .session-item').forEach(el => {
        el.classList.toggle('search-hidden', term !== '' && !el.dataset.title.includes(term));
    });
}

// ── GDPR CONSENT ──
function getCookie(name) {
    return document.cookie.split('; ').find(r => r.startsWith(name + '='))?.split('=')[1];
}
function setCookie(name, value, days) {
    document.cookie = `${name}=${value}; max-age=${days * 86400}; path=/; SameSite=Lax`;
}
@if(($gdpr['enabled'] ?? false) && $activeSession)
if (!getCookie('laravelai_gdpr_consent')) {
    document.getElementById('gdprOverlay')?.classList.add('open');
}
@endif
function acceptGdpr() {
    setCookie('laravelai_gdpr_consent', '1', 365);
    document.getElementById('gdprOverlay')?.classList.remove('open');
}

// ── CAPTCHA ──
async function ensureCaptcha() {
    if (!CAPTCHA_ENABLED || HAS_MESSAGES) return;
    const r = await fetch('{{ route("ai-chat.captcha") }}');
    const d = await r.json();
    captchaToken = d.token;
    document.getElementById('captchaQuestion').textContent = d.question + ' =';
    document.getElementById('captchaRow')?.classList.remove('hidden');
}
@if($activeSession)
ensureCaptcha();
@endif

// ── SEND MESSAGE (fetch + manual SSE parsing — needed so blocked/rate-limited
//    JSON error responses are readable; native EventSource can't expose them) ──
function sendSuggested(text) {
    document.getElementById('input').value = text;
    sendOrStop();
}

function sendOrStop() {
    if (isStreaming) { stopStreaming(); return; }
    sendMessage(false);
}

function stopStreaming() {
    currentAbortController?.abort();
    isStreaming = false;
    setSendButtonState(false);
}

function setSendButtonState(streaming) {
    const btn = document.getElementById('sendBtn');
    if (!btn) return;
    btn.disabled = false;
    btn.classList.toggle('stop-btn', streaming);
    btn.textContent = streaming ? '■' : '▶';
    btn.title = streaming ? 'Stop' : 'Send';
}

async function sendMessage(isRegenerate, regenText) {
    if (!SESSION_ID || isStreaming) return;
    const input = document.getElementById('input');
    const text  = isRegenerate ? (regenText || '') : input.value.trim();
    if (!text) return;

    if (!isRegenerate) {
        input.value = '';
        input.style.height = 'auto';
        appendMsg('user', text);
    }

    document.querySelector('.regen-row')?.remove();

    isStreaming = true;
    setSendButtonState(true);

    const div = appendMsg('assistant', '');
    div.classList.add('typing-cursor');
    const bubbleWrap = div.closest('.bubble-wrap');

    const payload = {
        message: text,
        session_id: SESSION_ID,
        regenerate: isRegenerate ? 1 : 0,
    };
    if (pendingAttachments.length) {
        payload.attachment_ids = pendingAttachments.map(a => a.id);
    }
    if (captchaToken && !HAS_MESSAGES) {
        payload.cap_token = captchaToken;
        payload.cap_answer = document.getElementById('captchaAnswer')?.value || '';
    }

    currentAbortController = new AbortController();
    let assistantId = null;
    let thinkBlock = null, thinkBody = null, thinkStart = null, thinkTimer = null;

    function finalizeThinking() {
        if (!thinkTimer) return;
        clearInterval(thinkTimer);
        thinkTimer = null;
        const secs = Math.max(1, Math.round((Date.now() - thinkStart) / 1000));
        thinkBlock.classList.remove('live');
        thinkBlock.querySelector('summary').innerHTML = `🧠 Thought for ${secs}s`;
        thinkBlock.open = false;
    }

    try {
        const response = await fetch('{{ route("ai-chat.stream") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'text/event-stream' },
            body: JSON.stringify(payload),
            signal: currentAbortController.signal,
        });

        if (!response.ok) {
            let msg = 'Request failed.';
            try { msg = (await response.json()).error || msg; } catch (e) {}
            div.textContent = '⚠ ' + msg;
            div.classList.remove('typing-cursor');
            finishStreaming();
            return;
        }

        const reader  = response.body.getReader();
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
                if (!line) continue;
                if (line === '[DONE]') { done = true; break; }

                let d;
                try { d = JSON.parse(line); } catch (e) { continue; }

                if (d.error) {
                    finalizeThinking();
                    div.textContent = '⚠ ' + d.error;
                    div.classList.remove('typing-cursor');
                } else if (d.thinking) {
                    if (!thinkBlock) {
                        thinkStart = Date.now();
                        thinkBlock = document.createElement('details');
                        thinkBlock.className = 'think-block live';
                        thinkBlock.open = true;
                        thinkBlock.innerHTML = `<summary>🧠 Thinking… <span class="think-timer">0s</span></summary><div class="think-body"></div>`;
                        thinkBody = thinkBlock.querySelector('.think-body');
                        bubbleWrap.insertBefore(thinkBlock, div.closest('.bubble'));
                        thinkTimer = setInterval(() => {
                            const el = thinkBlock.querySelector('.think-timer');
                            if (el) el.textContent = Math.round((Date.now() - thinkStart) / 1000) + 's';
                        }, 500);
                    }
                    thinkBody.textContent += d.thinking;
                    thinkBody.scrollTop = thinkBody.scrollHeight;
                    scrollBottom();
                } else if (d.text) {
                    finalizeThinking();
                    div.dataset.raw = (div.dataset.raw || '') + d.text;
                    div.innerHTML = marked.parse(div.dataset.raw);
                    scrollBottom();
                } else if (d.assistant_id) {
                    assistantId = d.assistant_id;
                } else if (d.title) {
                    updateSessionTitle(SESSION_ID, d.title);
                }
            }
        }

        HAS_MESSAGES = true;
        document.getElementById('captchaRow')?.classList.add('hidden');
    } catch (e) {
        if (e.name !== 'AbortError') {
            div.textContent = '⚠ Connection error.';
        }
    } finally {
        finalizeThinking();
        div.classList.remove('typing-cursor');
        if (bubbleWrap && assistantId) {
            bubbleWrap.dataset.msgId = assistantId;
            addAssistantActions(bubbleWrap, div.dataset.raw || '', assistantId);
        }
        pendingAttachments = [];
        renderAttachChips();
        finishStreaming();
        attachRegenerateButton();
    }
}

function finishStreaming() {
    isStreaming = false;
    setSendButtonState(false);
}

function updateSessionTitle(id, title) {
    document.querySelector('.header-title') && (document.querySelector('.header-title').textContent = title);
    const link = document.getElementById('session-link-' + id);
    if (link) {
        const span = link.querySelector('.session-title');
        if (span) span.textContent = title;
        link.dataset.title = title.toLowerCase();
    }
}

function appendMsg(role, text) {
    const wrap = document.createElement('div');
    wrap.className = 'msg-row ' + role;
    const av = document.createElement('div');
    av.className = 'avatar ' + role;
    av.textContent = role === 'user' ? 'You' : 'AI';
    const bwrap = document.createElement('div');
    bwrap.className = 'bubble-wrap';
    bwrap.dataset.role = role;

    const meta = document.createElement('div');
    meta.className = 'msg-meta';
    meta.innerHTML = `<span class="msg-label">${role === 'user' ? 'You' : 'Assistant'}</span>
                       <span class="msg-time">${new Date().toTimeString().slice(0,5)}</span>`;

    const bub = document.createElement('div');
    bub.className = 'bubble ' + role;
    const md = document.createElement('div');
    md.className = 'md-body';
    if (role === 'user') { md.textContent = text; }
    else { md.dataset.raw = text; md.innerHTML = text ? marked.parse(text) : ''; }
    bub.appendChild(md);

    bwrap.appendChild(meta);
    bwrap.appendChild(bub);
    wrap.appendChild(av);
    wrap.appendChild(bwrap);
    msgEl.appendChild(wrap);
    scrollBottom();
    return md;
}

function addAssistantActions(bubbleWrap, content, msgId) {
    const actions = document.createElement('div');
    actions.className = 'bubble-actions';
    actions.innerHTML = `
        <button class="icon-action" onclick="copyText(this, ${JSON.stringify(content)})">Copy</button>
        <button class="icon-action" onclick="downloadText(${JSON.stringify(content)}, 'message-${msgId}.txt')">⬇ Save</button>
        @if($ui['tts_enabled'] ?? true)
        <button class="icon-action" onclick="toggleReadAloud(this, ${JSON.stringify(content)})">🔊 Read</button>
        @endif
        <button class="icon-action feedback-up" onclick="sendFeedback(${msgId}, 1, this)">👍</button>
        <button class="icon-action feedback-down" onclick="sendFeedback(${msgId}, -1, this)">👎</button>
    `;
    bubbleWrap.appendChild(actions);
}

// ── REGENERATE ──
function attachRegenerateButton() {
    document.querySelector('.regen-row')?.remove();
    const rows = document.querySelectorAll('.msg-row.assistant');
    const last = rows[rows.length - 1];
    if (!last) return;

    const userRows = document.querySelectorAll('.msg-row.user');
    const lastUser = userRows[userRows.length - 1];
    if (!lastUser) return;
    const lastUserText = lastUser.querySelector('.md-body')?.textContent || '';

    const row = document.createElement('div');
    row.className = 'regen-row';
    row.innerHTML = `<button class="regen-btn" onclick="sendMessage(true, ${JSON.stringify(lastUserText)})">🔄 Regenerate</button>`;
    last.after(row);
}
@if($activeSession && $messages->isNotEmpty())
attachRegenerateButton();
@endif

// ── FEEDBACK ──
async function sendFeedback(msgId, rating, btn) {
    const r = await fetch(`{{ url('ai-chat/api/messages') }}/${msgId}/feedback`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ rating }),
    });
    if (!r.ok) return;
    const group = btn.closest('.bubble-actions');
    group.querySelectorAll('.feedback-up, .feedback-down').forEach(b => b.classList.remove('rated'));
    btn.classList.add('rated');
}

// ── TTS (read aloud) ──
let currentUtterance = null;
function toggleReadAloud(btn, text) {
    if (!('speechSynthesis' in window)) { alert('Text-to-speech is not supported in this browser.'); return; }
    if (currentUtterance && speechSynthesis.speaking) {
        speechSynthesis.cancel();
        currentUtterance = null;
        btn.textContent = '🔊 Read';
        return;
    }
    const plain = text.replace(/[#*`_>\-]/g, '').replace(/\n{2,}/g, '. ');
    currentUtterance = new SpeechSynthesisUtterance(plain);
    currentUtterance.onend = () => { btn.textContent = '🔊 Read'; currentUtterance = null; };
    btn.textContent = '⏸ Stop';
    speechSynthesis.speak(currentUtterance);
}

// ── VOICE INPUT ──
let recognition = null;
let recording = false;
function toggleVoiceInput() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { alert('Voice input is not supported in this browser (try Chrome, Edge, or Safari).'); return; }
    const micBtn = document.getElementById('micBtn');
    if (recording) {
        recognition?.stop();
        return;
    }
    recognition = new SR();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.onstart = () => { recording = true; micBtn.classList.add('recording'); };
    recognition.onend = () => { recording = false; micBtn.classList.remove('recording'); };
    recognition.onresult = (e) => {
        const input = document.getElementById('input');
        input.value = (input.value ? input.value + ' ' : '') + e.results[0][0].transcript;
        input.dispatchEvent(new Event('input'));
    };
    recognition.start();
}

// ── FULLSCREEN ──
function toggleFullscreen() {
    document.body.classList.toggle('chat-fullscreen');
    const btn = document.getElementById('fullscreenBtn');
    if (btn) btn.classList.toggle('active');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.body.classList.contains('chat-fullscreen')) {
        document.body.classList.remove('chat-fullscreen');
    }
});

// ── EXPORT ──
function downloadText(text, filename) {
    const blob = new Blob([text], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
}
function exportConversation() {
    const lines = [];
    document.querySelectorAll('.msg-row').forEach(row => {
        const role = row.classList.contains('user') ? 'You' : 'Assistant';
        const text = row.querySelector('.md-body')?.textContent || '';
        lines.push(`[${role}]\n${text}\n`);
    });
    downloadText(lines.join('\n'), 'conversation-{{ $activeSession?->id }}.txt');
}

// ── ATTACHMENTS ──
function renderAttachChips() {
    const el = document.getElementById('attachChips');
    if (!el) return;
    el.innerHTML = pendingAttachments.map(a => `
        <span class="attach-chip">${a.type === 'image' ? '🖼️' : '📄'} ${a.name}
            <button onclick="removeAttachment(${a.id})">✕</button>
        </span>
    `).join('');
}
function removeAttachment(id) {
    pendingAttachments = pendingAttachments.filter(a => a.id !== id);
    renderAttachChips();
}
async function uploadAttachment(input) {
    if (!input.files[0] || !SESSION_ID) return;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('session_id', SESSION_ID);

    const r = await fetch('{{ route("ai-chat.attachments.store") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: formData,
    });
    input.value = '';
    const d = await r.json();
    if (!r.ok) { alert(d.error || 'Upload failed'); return; }
    pendingAttachments.push(d);
    renderAttachChips();
}

// ── INPUT EVENTS ──
@if($activeSession)
document.getElementById('input').addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); sendOrStop(); }
});
document.getElementById('input').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 160) + 'px';
});
document.getElementById('welcomeMsg') && (document.getElementById('welcomeMsg').innerHTML = marked.parse(document.getElementById('welcomeMsg').dataset.raw));
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
            ${f.status === 'ingested' ? `<button class="file-item-del" title="Reprocess" onclick="reprocessFile(${projectId}, ${f.id})">↻</button>` : ''}
            <button class="file-item-del" title="Delete" onclick="deleteFile(${projectId}, ${f.id})">✕</button>
        </div>
    `).join('');
}

async function reprocessFile(projectId, fileId) {
    await fetch(`{{ url("ai-chat/api/projects") }}/${projectId}/files/${fileId}/reprocess`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    await loadFiles(projectId);
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
