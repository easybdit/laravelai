<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Settings — LaravelAI</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f5f6fa; color: #1a1d27; padding: 32px 20px 80px; }
        .wrap { max-width: 760px; margin: 0 auto; }
        .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        h1 { font-size: 1.25rem; font-weight: 700; }
        a.back { color: #6366f1; text-decoration: none; font-size: 0.85rem; }
        .sub { color: #6b7280; font-size: 0.85rem; margin-bottom: 24px; }
        .status { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; }
        .card { background: #fff; border: 1px solid #e8eaef; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .card h2 { font-size: 0.92rem; font-weight: 650; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; }
        .default-badge { font-size: 0.65rem; font-weight: 600; background: #eef2ff; color: #4f46e5; padding: 2px 8px; border-radius: 20px; }
        .field-row { display: grid; grid-template-columns: 100px 1fr; gap: 10px; align-items: center; margin-bottom: 10px; }
        .field-row label { font-size: 0.8rem; color: #6b7280; }
        .field-row input, .field-row select { width: 100%; padding: 7px 10px; border: 1px solid #e0dced; border-radius: 8px; font-size: 0.85rem; font-family: inherit; outline: none; }
        .field-row input:focus, .field-row select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .card-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .test-btn { font-size: 0.76rem; background: #f5f6fa; border: 1px solid #e0dced; padding: 5px 12px; border-radius: 8px; cursor: pointer; color: #4b5563; }
        .test-btn:hover { background: #eef2ff; color: #4f46e5; }
        .test-result { font-size: 0.76rem; margin-left: 8px; }
        .test-result.ok { color: #059669; }
        .test-result.fail { color: #dc2626; }
        .top-controls { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
        .top-controls select { padding: 8px 12px; border-radius: 8px; border: 1px solid #e0dced; font-size: 0.85rem; font-family: inherit; }
        .save-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e8eaef; padding: 14px 20px; margin: 24px -20px -80px; display: flex; justify-content: flex-end; }
        .save-btn { background: #6366f1; color: #fff; border: none; padding: 10px 22px; border-radius: 9px; font-size: 0.88rem; font-weight: 600; cursor: pointer; }
        .save-btn:hover { background: #4f46e5; }
        .hint { font-size: 0.72rem; color: #9ca3af; margin-top: -4px; margin-bottom: 14px; }
        .admin-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f0f0f5; font-size: 0.85rem; }
        .admin-row:last-child { border-bottom: none; }
        .you-badge { font-size: 0.65rem; font-weight: 600; background: #eef2ff; color: #4f46e5; padding: 1px 7px; border-radius: 20px; margin-left: 6px; }
        .remove-btn { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.78rem; padding: 2px 4px; }
        .remove-btn:hover { text-decoration: underline; }
        .add-admin-form { display: flex; gap: 8px; margin-top: 12px; }
        .add-admin-form input { flex: 1; padding: 7px 10px; border: 1px solid #e0dced; border-radius: 8px; font-size: 0.85rem; font-family: inherit; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>⚙️ AI Settings</h1>
        <a class="back" href="{{ route('ai-chat.index') }}">← Back to chat</a>
    </div>
    <p class="sub">Changes here override your <code>.env</code> configuration immediately — leave a field blank to fall back to it again.</p>

    @if($status)
        <div class="status">✅ {{ $status }}</div>
    @endif

    <div class="card">
        <h2>👤 Admin Access</h2>
        <p class="hint" style="margin-top:0;">Who can reach this page. Add more people by email — no code changes, no redeploy.</p>

        @if(count($admins))
            <div>
                @foreach($admins as $admin)
                    <div class="admin-row">
                        <span>
                            {{ $admin['email'] }}
                            @if($admin['user_id'] === $currentUserId)
                                <span class="you-badge">YOU</span>
                            @endif
                        </span>
                        <form method="POST" action="{{ route('ai-chat.settings.admins.remove', $admin['id']) }}"
                              onsubmit="return confirm('Remove admin access for {{ $admin['email'] }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="remove-btn">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('ai-chat.settings.admins.add') }}" class="add-admin-form">
            @csrf
            <input type="email" name="email" placeholder="email@example.com — must already have an account" required>
            <button type="submit" class="test-btn">+ Add admin</button>
        </form>
    </div>

    <form method="POST" action="{{ route('ai-chat.settings.update') }}">
        @csrf

        <div class="top-controls">
            <label for="default_provider" style="font-size:0.85rem;color:#6b7280;">Default provider</label>
            <select name="default_provider" id="default_provider">
                @foreach($providerLabels as $key => $label)
                    <option value="{{ $key }}" {{ $defaultProvider === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @foreach($providers as $name => $fields)
            <div class="card">
                <h2>
                    {{ $providerLabels[$name] }}
                    @if($defaultProvider === $name)<span class="default-badge">DEFAULT</span>@endif
                </h2>

                @foreach($fields as $field => $value)
                    <div class="field-row">
                        <label>{{ ucfirst(str_replace('_', ' ', $field)) }}</label>
                        @if(in_array($field, $booleanFields))
                            <div>
                                <input type="hidden" name="providers[{{ $name }}][{{ $field }}]" value="0">
                                <input
                                    type="checkbox"
                                    name="providers[{{ $name }}][{{ $field }}]"
                                    value="1"
                                    {{ $value ? 'checked' : '' }}
                                    style="width:auto;">
                            </div>
                        @else
                            <input
                                type="{{ in_array($field, $secretFields) ? 'password' : 'text' }}"
                                name="providers[{{ $name }}][{{ $field }}]"
                                value="{{ $value }}"
                                placeholder="{{ in_array($field, $secretFields) ? '(unchanged if left as-is)' : '' }}"
                                autocomplete="off">
                        @endif
                    </div>
                @endforeach

                <div class="card-actions">
                    <button type="button" class="test-btn" onclick="testProvider('{{ $name }}', this)">Test connection</button>
                    <span class="test-result" id="test-{{ $name }}"></span>
                </div>
            </div>
        @endforeach

        <div class="save-bar">
            <button type="submit" class="save-btn">Save settings</button>
        </div>
    </form>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
async function testProvider(name, btn) {
    const result = document.getElementById('test-' + name);
    result.className = 'test-result';
    result.textContent = 'Testing…';
    btn.disabled = true;
    try {
        const r = await fetch('{{ route("ai-chat.settings.test") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ provider: name }),
        });
        const d = await r.json();
        result.className = 'test-result ' + (d.ok ? 'ok' : 'fail');
        result.textContent = d.ok ? '✓ Connected' : ('✗ ' + (d.error || 'Failed'));
    } catch (e) {
        result.className = 'test-result fail';
        result.textContent = '✗ Connection error';
    } finally {
        btn.disabled = false;
    }
}
</script>
</body>
</html>
