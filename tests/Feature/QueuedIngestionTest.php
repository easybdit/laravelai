<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\RAG\Jobs\IngestDocumentJob;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * RAG document ingestion previously always ran synchronously inline in the
 * upload request — for a large document that meant the request blocked for
 * as long as it took to embed every chunk sequentially. These cover both
 * the unchanged default (config('ai.rag.queue_ingestion') === false) and
 * the new opt-in queued path.
 */
class QueuedIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function withGuestCookie(string $token): array
    {
        return [ChatIdentity::COOKIE_NAME => $token];
    }

    private function fakeEmbedEndpoint(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [[1, 0, 0]]]),
        ]);
    }

    private function uploadFile(Project $project, string $guestToken)
    {
        Storage::fake('local');

        // Unique filename per call, not a shared 'notes.txt' literal — two
        // tests in this class both create a fake upload in quick
        // succession, and a real temp-file collision/reuse between them
        // is a cheap thing to rule out as a variable regardless of whether
        // it's the actual cause of any given flake.
        $file = UploadedFile::fake()->createWithContent(uniqid('notes_', true) . '.txt', 'Some file content to ingest.');

        return $this->withCredentials()
            ->withCookies($this->withGuestCookie($guestToken))
            ->post("/ai-chat/api/projects/{$project->id}/files", ['file' => $file]);
    }

    public function test_default_behavior_ingests_synchronously_and_marks_the_file_ingested(): void
    {
        // Default config — must be byte-identical to pre-existing behavior.
        config(['ai.rag.queue_ingestion' => false]);
        $this->fakeEmbedEndpoint();

        $guestToken = str_repeat('a', 40);
        $project    = Project::create(['name' => 'Mine', 'guest_token' => $guestToken]);

        $response = $this->uploadFile($project, $guestToken);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'ingested');

        $this->assertDatabaseHas('ai_project_files', [
            'project_id' => $project->id,
            'status'     => 'ingested',
        ]);

        $this->assertGreaterThan(
            0,
            \Illuminate\Support\Facades\DB::table(config('ai.rag.table', 'ai_documents'))
                ->where('source', 'project_' . $project->id)
                ->count()
        );
    }

    public function test_queued_config_dispatches_the_ingest_job_and_marks_the_file_queued(): void
    {
        config(['ai.rag.queue_ingestion' => true]);
        Queue::fake();
        $this->fakeEmbedEndpoint();

        $guestToken = str_repeat('b', 40);
        $project    = Project::create(['name' => 'Mine', 'guest_token' => $guestToken]);

        $response = $this->uploadFile($project, $guestToken);

        // TEMPORARY: PHP 8.1 x Laravel 10 CI leg gets a real, reproducible
        // 422 here with no visible cause locally — dumping the actual
        // response body into the assertion message (assertStatus() itself
        // takes no message argument) instead of guessing at a fix blind.
        // Remove once the real cause is known.
        $this->assertSame(201, $response->status(), 'Got ' . $response->status() . ' instead: ' . $response->getContent());
        $response->assertJsonPath('status', 'queued');

        $this->assertDatabaseHas('ai_project_files', [
            'project_id' => $project->id,
            'status'     => 'queued',
        ]);

        Queue::assertPushed(IngestDocumentJob::class, function (IngestDocumentJob $job) use ($project) {
            return $job->source === 'project_' . $project->id
                && str_contains($job->content, 'Some file content to ingest.');
        });

        // The job hasn't actually run in this test, so no rows should exist yet.
        $this->assertSame(
            0,
            \Illuminate\Support\Facades\DB::table(config('ai.rag.table', 'ai_documents'))
                ->where('source', 'project_' . $project->id)
                ->count()
        );
    }
}
