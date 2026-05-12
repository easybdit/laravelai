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
}