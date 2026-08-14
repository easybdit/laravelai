<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Zero-external-tracking usage dashboard — every number here comes from
 * the ai_chat_sessions / ai_chat_messages tables the package already writes to,
 * mirroring the WordPress plugin's Analytics page.
 */
class AnalyticsController extends Controller
{
    public function index()
    {
        $totalConversations = ChatSession::count();
        $totalMessages       = ChatMessage::count();
        $messagesToday       = ChatMessage::whereDate('created_at', Carbon::today())->count();
        $activeChats7d       = ChatSession::whereHas('messages', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))->count();

        $mostUsedProvider = ChatSession::whereNotNull('provider')
            ->selectRaw('provider, COUNT(*) as total')
            ->groupBy('provider')
            ->orderByDesc('total')
            ->first()?->provider;

        $thumbsUp   = ChatMessage::where('rating', 1)->count();
        $thumbsDown = ChatMessage::where('rating', -1)->count();

        $dailyCounts = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return [
                'label' => $date->format('D'),
                'count' => ChatMessage::whereDate('created_at', $date)->count(),
            ];
        });
        $peak = max(1, $dailyCounts->max('count'));

        return view('laravelai::analytics', [
            'totalConversations' => $totalConversations,
            'totalMessages'      => $totalMessages,
            'messagesToday'      => $messagesToday,
            'activeChats7d'      => $activeChats7d,
            'mostUsedProvider'   => $mostUsedProvider ?? '—',
            'thumbsUp'           => $thumbsUp,
            'thumbsDown'         => $thumbsDown,
            'dailyCounts'        => $dailyCounts,
            'peak'               => $peak,
        ]);
    }
}
