<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProjectController extends Controller
{
    /**
     * Projects previously had no owner at all — index() returned every
     * project on the install to every visitor, and destroy() below let
     * anyone delete anyone's. Scoped the same way chat_sessions already
     * were: owned by the resolved identity, or predating the identity
     * migration entirely (stays visible rather than vanishing on upgrade).
     */
    private function scopeToIdentity(Builder $query, ?int $userId, ?string $guestToken): void
    {
        $query->where(function ($q) use ($userId, $guestToken) {
            if ($userId) {
                $q->where('user_id', $userId);
            } elseif ($guestToken) {
                $q->where('guest_token', $guestToken);
            } else {
                $q->whereRaw('1 = 0');
            }

            $q->orWhere(function ($q2) {
                $q2->whereNull('user_id')->whereNull('guest_token');
            });
        });
    }

    public function index(Request $request)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);

        $query = Project::withCount('files');
        $this->scopeToIdentity($query, $userId, $guestToken);

        $limit = max(1, (int) config('ai.chat.sidebar_limit', 100));

        return response()->json($query->latest()->limit($limit)->get());
    }

    public function store(Request $request)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $guestToken = $userId ? null : ChatIdentity::ensureGuestToken($guestToken);

        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $project = Project::create([
            ...$request->only('name', 'description'),
            'user_id'     => $userId,
            'guest_token' => $guestToken,
        ]);

        return response()->json($project, 201);
    }

    public function destroy(Request $request, $project)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);

        $proj = Project::findOrFail($project);
        if (!$proj->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        try {
            AI::rag()->flush('project_' . $proj->id);
        } catch (\Throwable $e) {
            // Non-fatal
        }

        $proj->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Admin test-query tool — the Laravel port of the WordPress Knowledge
     * Base "Test Query" panel. Inspect which chunks a query would retrieve
     * (and the accurate full-corpus count for "how many X" questions)
     * before shipping it in front of real users.
     *
     * When targeting a specific project's source, ownership is enforced
     * exactly like every other project-scoped endpoint — otherwise any
     * visitor could inspect the contents of documents uploaded to a
     * project they don't own via this endpoint alone. A blank/global
     * source (no project targeted) stays unscoped, matching this
     * endpoint's documented role as a general admin tool.
     */
    public function testQuery(Request $request)
    {
        $request->validate([
            'query'  => 'required|string',
            'source' => 'nullable|string',
        ]);

        $source = $request->input('source') ?: null;

        if ($source && preg_match('/^project_(\d+)$/', $source, $m)) {
            [$userId, $guestToken] = ChatIdentity::resolve($request);
            $proj = Project::find((int) $m[1]);
            if (!$proj || !$proj->isOwnedBy($userId, $guestToken)) {
                abort(403);
            }
        }

        $rag = AI::rag();
        if ($source) {
            $rag->source($source);
        }

        return response()->json([
            'query'   => $request->input('query'),
            'count'   => $rag->countMatches($request->input('query'), $source),
            'results' => $rag->search($request->input('query')),
        ]);
    }
}
