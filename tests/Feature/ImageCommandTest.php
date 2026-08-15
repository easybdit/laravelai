<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatAttachment;
use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The "/image {prompt}" chat command (streamImageGeneration()) — Together's
 * returned URL is a temporary pickup link (confirmed expiring within hours
 * in real use, not permanent hosting), so a stored chat message pointing
 * straight at it goes permanently broken later. These lock in the fix:
 * mirror the generated image into this app's own attachment storage
 * immediately, same as a user-uploaded image, so the chat history stays
 * valid indefinitely.
 */
class ImageCommandTest extends TestCase
{
    use RefreshDatabase;

    private function enableTogetherImages(): void
    {
        config([
            'ai.providers.together.image_enabled' => true,
            'ai.providers.together.api_key'       => 'test-key',
        ]);
    }

    private function streamImage(ChatSession $session, string $prompt): string
    {
        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => "/image {$prompt}",
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        return $response->streamedContent();
    }

    /** The first "data: {...}" SSE event's "text" field, JSON-decoded (json_encode() escapes slashes, so raw string matching against the wire content is fragile). */
    private function firstTextEvent(string $streamed): string
    {
        preg_match('/^data: (\{.*\})$/m', $streamed, $m);
        $this->assertNotEmpty($m, 'No "data: {...}" SSE event found in the streamed content.');
        return json_decode($m[1], true)['text'] ?? '';
    }

    public function test_generated_image_is_mirrored_to_local_attachment_storage(): void
    {
        Storage::fake('local');
        $this->enableTogetherImages();

        Http::fake([
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [['url' => 'https://together.example/temp/flux-generated.png']],
            ]),
            'together.example/temp/flux-generated.png' => Http::response(
                'fake-png-bytes', 200, ['Content-Type' => 'image/png']
            ),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);
        $content = $this->streamImage($session, 'a red fox in snow');

        $this->assertStringContainsString('[DONE]', $content);

        $attachment = ChatAttachment::where('chat_session_id', $session->id)->first();
        $this->assertNotNull($attachment, 'Expected the generated image to be persisted as a ChatAttachment.');
        $this->assertSame('image', $attachment->type);
        $this->assertSame('image/png', $attachment->mime_type);
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertSame('fake-png-bytes', Storage::disk('local')->get($attachment->stored_path));

        // The streamed markdown — and the persisted assistant message —
        // must point at this app's own attachment route, never the
        // provider's temporary URL directly.
        $streamedText = $this->firstTextEvent($content);
        $this->assertStringContainsString("/ai-chat/api/attachments/{$attachment->id}", $streamedText);
        $this->assertStringNotContainsString('together.example', $streamedText);

        $this->assertDatabaseHas('ai_chat_messages', [
            'chat_session_id' => $session->id,
            'role'            => 'assistant',
        ]);
        $assistantContent = ChatMessage::where('chat_session_id', $session->id)
            ->where('role', 'assistant')->value('content');
        $this->assertStringContainsString("/ai-chat/api/attachments/{$attachment->id}", $assistantContent);
        $this->assertStringNotContainsString('together.example', $assistantContent);

        // And the attachment ends up linked back to that exact message.
        $this->assertSame(
            ChatMessage::where('chat_session_id', $session->id)->where('role', 'assistant')->value('id'),
            $attachment->fresh()->chat_message_id
        );
    }

    public function test_download_failure_falls_back_to_the_provider_url_without_crashing(): void
    {
        Storage::fake('local');
        $this->enableTogetherImages();

        Http::fake([
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [['url' => 'https://together.example/temp/flux-generated.png']],
            ]),
            'together.example/temp/flux-generated.png' => Http::response('', 500),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);
        $content = $this->streamImage($session, 'a red fox in snow');

        $this->assertStringContainsString('[DONE]', $content);
        $this->assertStringContainsString(
            'https://together.example/temp/flux-generated.png',
            $this->firstTextEvent($content)
        );
        $this->assertSame(0, ChatAttachment::count());
    }

    public function test_no_storage_mode_never_persists_an_attachment(): void
    {
        Storage::fake('local');
        $this->enableTogetherImages();
        config(['ai.chat.disable_storage' => true]);

        Http::fake([
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [['url' => 'https://together.example/temp/flux-generated.png']],
            ]),
            'together.example/temp/flux-generated.png' => Http::response(
                'fake-png-bytes', 200, ['Content-Type' => 'image/png']
            ),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);
        $content = $this->streamImage($session, 'a red fox in snow');

        $this->assertStringContainsString('[DONE]', $content);
        $this->assertStringContainsString(
            'https://together.example/temp/flux-generated.png',
            $this->firstTextEvent($content)
        );
        $this->assertSame(0, ChatAttachment::count());
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }
}
