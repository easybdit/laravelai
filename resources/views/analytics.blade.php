<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — LaravelAI</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f5f6fa; color: #1a1d27; padding: 32px; }
        .wrap { max-width: 1000px; margin: 0 auto; }
        .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        h1 { font-size: 1.25rem; font-weight: 700; }
        a.back { color: #6366f1; text-decoration: none; font-size: 0.85rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: #fff; border: 1px solid #e8eaef; border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .stat-num { font-size: 1.7rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .stat-label { font-size: 0.76rem; color: #8b95a8; margin-top: 4px; }
        .panel { background: #fff; border: 1px solid #e8eaef; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .panel h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 16px; }
        .bars { display: flex; align-items: flex-end; gap: 10px; height: 140px; }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 6px; }
        .bar { width: 100%; background: linear-gradient(180deg, #6366f1, #8b5cf6); border-radius: 6px 6px 0 0; min-height: 3px; }
        .bar-label { font-size: 0.7rem; color: #8b95a8; }
        .fb-row { display: flex; gap: 20px; }
        .fb-stat { flex: 1; text-align: center; padding: 14px; border-radius: 10px; }
        .fb-up { background: #ecfdf5; color: #059669; }
        .fb-down { background: #fef2f2; color: #dc2626; }
        .fb-num { font-size: 1.4rem; font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>📊 Analytics</h1>
        <a class="back" href="{{ route('ai-chat.index') }}">← Back to chat</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-num">{{ number_format($totalConversations) }}</div><div class="stat-label">Total conversations</div></div>
        <div class="stat-card"><div class="stat-num">{{ number_format($totalMessages) }}</div><div class="stat-label">Total messages</div></div>
        <div class="stat-card"><div class="stat-num">{{ number_format($messagesToday) }}</div><div class="stat-label">Messages today</div></div>
        <div class="stat-card"><div class="stat-num">{{ number_format($activeChats7d) }}</div><div class="stat-label">Active chats (7d)</div></div>
        <div class="stat-card"><div class="stat-num" style="font-size:1.1rem;">{{ ucfirst($mostUsedProvider) }}</div><div class="stat-label">Most-used provider</div></div>
    </div>

    <div class="panel">
        <h2>Messages — last 7 days</h2>
        <div class="bars">
            @foreach($dailyCounts as $d)
                <div class="bar-col">
                    <div class="bar" style="height: {{ max(3, ($d['count'] / $peak) * 120) }}px" title="{{ $d['count'] }} messages"></div>
                    <div class="bar-label">{{ $d['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="panel">
        <h2>Feedback</h2>
        <div class="fb-row">
            <div class="fb-stat fb-up"><div class="fb-num">👍 {{ $thumbsUp }}</div><div>Helpful</div></div>
            <div class="fb-stat fb-down"><div class="fb-num">👎 {{ $thumbsDown }}</div><div>Not helpful</div></div>
        </div>
    </div>
</div>
</body>
</html>
