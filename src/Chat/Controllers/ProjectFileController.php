<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Chat\Models\ProjectFile;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Chat\Support\TextExtractor;
use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectFileController extends Controller
{
    /**
     * Every action here operates on a specific project's files, so every
     * action needs the same ownership check — findOrFail() alone (the
     * previous behavior) let any visitor read/upload/delete files for
     * *any* project on the install, same gap ProjectController had.
     */
    private function findOwnedProject(Request $request, $project): Project
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);

        $proj = Project::findOrFail($project);
        if (!$proj->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        return $proj;
    }

    public function index(Request $request, $project)
    {
        $proj = $this->findOwnedProject($request, $project);
        return response()->json($proj->files()->latest()->get());
    }

    public function store(Request $request, $project)
    {
        $proj = $this->findOwnedProject($request, $project);

        $request->validate([
            'file' => 'required|file|mimes:txt,md,pdf|max:10240',
        ]);

        $uploadedFile = $request->file('file');
        $path         = $uploadedFile->store('project-files/' . $proj->id, 'local');

        $pf = ProjectFile::create([
            'project_id'    => $proj->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $uploadedFile->getMimeType(),
            'status'        => 'pending',
        ]);

        try {
            $text = $this->extractText($pf);

            if (empty(trim($text))) {
                throw new \RuntimeException('File is empty or could not be read.');
            }

            $source = 'project_' . $proj->id;
            AI::rag()->ingest($text, $source);

            $count = DB::table(config('ai.rag.table', 'ai_documents'))
                        ->where('source', $source)
                        ->count();

            if ($count === 0) {
                throw new \RuntimeException(
                    'Ingestion ran but no chunks stored. Check nomic-embed-text is running.'
                );
            }

            $pf->update(['status' => 'ingested']);

        } catch (\Throwable $e) {
            $pf->update(['status' => 'failed']);
            return response()->json([
                'file'  => $pf->fresh(),
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json($pf->fresh(), 201);
    }

    public function destroy(Request $request, $project, $file)
    {
        $proj = $this->findOwnedProject($request, $project);
        $pf   = ProjectFile::where('project_id', $proj->id)->findOrFail($file);

        Storage::disk('local')->delete($pf->stored_path);
        $pf->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Re-chunk an already-uploaded file in place — useful after changing
     * AI_RAG_CHUNK_SIZE / AI_RAG_EMBED_MODEL, without re-uploading anything.
     */
    public function reprocess(Request $request, $project, $file)
    {
        $proj   = $this->findOwnedProject($request, $project);
        $pf     = ProjectFile::where('project_id', $proj->id)->findOrFail($file);
        $source = 'project_' . $proj->id;

        try {
            $text = $this->extractText($pf);
            if (empty(trim($text))) {
                throw new \RuntimeException('File is empty or could not be read.');
            }

            // ai_documents has no per-file column — chunks are only scoped by
            // project source — so reprocessing one file means re-ingesting the
            // whole project's chunk set.
            DB::table(config('ai.rag.table', 'ai_documents'))->where('source', $source)->delete();

            foreach ($proj->files()->where('status', 'ingested')->where('id', '!=', $pf->id)->get() as $other) {
                AI::rag()->ingest($this->extractText($other), $source);
            }
            AI::rag()->ingest($text, $source);

            $pf->update(['status' => 'ingested']);
        } catch (\Throwable $e) {
            $pf->update(['status' => 'failed']);
            return response()->json(['file' => $pf->fresh(), 'error' => $e->getMessage()], 422);
        }

        return response()->json($pf->fresh());
    }

    private function extractText(ProjectFile $file): string
    {
        return TextExtractor::extract(Storage::disk('local')->path($file->stored_path), $file->mime_type);
    }
}
