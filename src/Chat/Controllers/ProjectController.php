<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProjectController extends Controller
{
    public function index()
    {
        return response()->json(Project::withCount('files')->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        return response()->json(Project::create($request->only('name', 'description')), 201);
    }

    public function destroy(Request $request, $project)
    {
        $proj = Project::findOrFail($project);

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
     */
    public function testQuery(Request $request)
    {
        $request->validate([
            'query'  => 'required|string',
            'source' => 'nullable|string',
        ]);

        $source = $request->input('source') ?: null;
        $rag    = AI::rag();
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