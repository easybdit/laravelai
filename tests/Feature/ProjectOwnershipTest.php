<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\Project;
use EasyAI\LaravelAI\Chat\Models\ProjectFile;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Projects previously had no owner at all — index() returned every project
 * on the install to every visitor, and destroy()/the file endpoints let
 * anyone read, upload to, or delete anyone else's project, no ownership
 * check anywhere. Found in a scalability/security audit alongside the
 * unbounded index() query these tests also cover.
 */
class ProjectOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function withGuestCookie(string $token): array
    {
        return [ChatIdentity::COOKIE_NAME => $token];
    }

    public function test_a_guest_only_sees_their_own_projects(): void
    {
        Project::create(['name' => 'Mine', 'guest_token' => str_repeat('a', 40)]);
        Project::create(['name' => 'Not mine', 'guest_token' => str_repeat('b', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->getJson('/ai-chat/api/projects');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Not mine'));
    }

    public function test_a_project_with_no_recorded_identity_is_visible_to_everyone(): void
    {
        Project::create(['name' => 'Legacy project']); // predates the identity migration

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('c', 40)))
            ->getJson('/ai-chat/api/projects');

        $response->assertOk();
        $this->assertTrue(collect($response->json())->pluck('name')->contains('Legacy project'));
    }

    public function test_a_guest_cannot_delete_a_project_they_do_not_own(): void
    {
        $project = Project::create(['name' => 'Not yours', 'guest_token' => str_repeat('a', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('b', 40)))
            ->deleteJson("/ai-chat/api/projects/{$project->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('ai_projects', ['id' => $project->id]);
    }

    public function test_a_guest_can_delete_their_own_project(): void
    {
        $project = Project::create(['name' => 'Mine to delete', 'guest_token' => str_repeat('a', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->deleteJson("/ai-chat/api/projects/{$project->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('ai_projects', ['id' => $project->id]);
    }

    public function test_project_files_are_scoped_the_same_way(): void
    {
        $theirs = Project::create(['name' => 'Theirs', 'guest_token' => str_repeat('b', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->getJson("/ai-chat/api/projects/{$theirs->id}/files");

        $response->assertStatus(403);
    }

    public function test_project_file_destroy_is_scoped_too(): void
    {
        $theirs = Project::create(['name' => 'Theirs', 'guest_token' => str_repeat('b', 40)]);
        $file   = ProjectFile::create([
            'project_id'    => $theirs->id,
            'original_name' => 'notes.txt',
            'stored_path'   => 'project-files/x/notes.txt',
            'status'        => 'ingested',
        ]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->deleteJson("/ai-chat/api/projects/{$theirs->id}/files/{$file->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('ai_project_files', ['id' => $file->id]);
    }

    public function test_index_respects_the_configured_sidebar_limit(): void
    {
        config(['ai.chat.sidebar_limit' => 2]);

        for ($i = 0; $i < 5; $i++) {
            Project::create(['name' => "Project {$i}", 'guest_token' => str_repeat('a', 40)]);
        }

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->getJson('/ai-chat/api/projects');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }
}
