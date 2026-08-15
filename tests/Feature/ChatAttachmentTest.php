<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use Dompdf\Dompdf;
use EasyAI\LaravelAI\Chat\Models\ChatAttachment;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Chat attachment uploads, including the PDF -> page-image vision path
 * (config('ai.chat.attachments.pdf_vision_enabled'), see PdfPageRenderer).
 * The real-rendering assertions are environment-gated the same way
 * PdfPageRendererTest's are — this machine has no imagick, CI does.
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function guestClient(): static
    {
        return $this->withCredentials()->withCookies(['laravelai_guest' => str_repeat('a', 40)]);
    }

    private function realPdfUpload(string $filename = 'policy.pdf'): UploadedFile
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<h1>Test PDF</h1><p>Content for ChatAttachmentTest.</p>');
        $dompdf->render();

        $path = tempnam(sys_get_temp_dir(), 'laraveleasyai-test-') . '.pdf';
        file_put_contents($path, $dompdf->output());

        return new UploadedFile($path, $filename, 'application/pdf', null, true);
    }

    public function test_pdf_upload_never_renders_page_images_when_the_feature_is_off(): void
    {
        Storage::fake('local');
        config(['ai.chat.attachments.enabled' => true]); // pdf_vision_enabled left at its default (false)

        $response = $this->guestClient()->post('/ai-chat/api/attachments', ['file' => $this->realPdfUpload()]);

        $response->assertOk();
        $this->assertSame(0, ChatAttachment::where('parent_attachment_id', $response->json('id'))->count());
    }

    public function test_pdf_upload_renders_page_images_when_enabled(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick is not installed on this machine — see .github/workflows/tests.yml, where it is.');
        }

        Storage::fake('local');
        config([
            'ai.chat.attachments.enabled'            => true,
            'ai.chat.attachments.pdf_vision_enabled' => true,
        ]);

        $response = $this->guestClient()->post('/ai-chat/api/attachments', ['file' => $this->realPdfUpload()]);
        $response->assertOk();

        $parentId = $response->json('id');
        $pages    = ChatAttachment::where('parent_attachment_id', $parentId)->get();

        $this->assertCount(1, $pages, 'The single-page test PDF should produce exactly one page-image attachment.');
        $this->assertSame('image', $pages->first()->type);
        $this->assertSame('image/png', $pages->first()->mime_type);
        Storage::disk('local')->assertExists($pages->first()->stored_path);
    }

    public function test_deleting_the_parent_pdf_also_deletes_its_page_images(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick is not installed on this machine — see .github/workflows/tests.yml, where it is.');
        }

        Storage::fake('local');
        config([
            'ai.chat.attachments.enabled'            => true,
            'ai.chat.attachments.pdf_vision_enabled' => true,
        ]);

        $client = $this->guestClient();
        $upload = $client->post('/ai-chat/api/attachments', ['file' => $this->realPdfUpload()]);
        $parentId = $upload->json('id');
        $pagePath = ChatAttachment::where('parent_attachment_id', $parentId)->first()->stored_path;

        $client->deleteJson("/ai-chat/api/attachments/{$parentId}")->assertOk();

        $this->assertSame(0, ChatAttachment::where('parent_attachment_id', $parentId)->count());
        Storage::disk('local')->assertMissing($pagePath);
    }

    /**
     * The core new capability, independent of PdfPageRenderer/imagick: once
     * a PDF has page-image children (however they got created), sending a
     * message with that PDF attached to a vision-capable provider puts
     * every page on the wire as real vision input, not just the first one.
     * Page images are inserted directly here rather than rendered for
     * real, so this exercises the resolveAttachments()/MessageFormatter
     * wiring on every machine, imagick or not.
     */
    public function test_a_pdfs_page_images_all_ride_along_as_vision_input(): void
    {
        Storage::fake('local');
        config(['ai.chat.attachments.enabled' => true]);

        $session = ChatSession::create(['title' => 'New Chat', 'provider' => 'openai']);

        $pdf = ChatAttachment::create([
            'chat_session_id' => $session->id,
            'type'            => 'document',
            'original_name'   => 'chart.pdf',
            'stored_path'     => 'chat-attachments/' . $session->id . '/chart.pdf',
            'mime_type'       => 'application/pdf',
            'size'            => 100,
            'extracted_text'  => 'Some extracted text.',
            'status'          => 'ready',
        ]);
        Storage::disk('local')->put($pdf->stored_path, 'fake pdf bytes');

        foreach ([1, 2] as $n) {
            $path = "chat-attachments/{$session->id}/page-{$n}.png";
            Storage::disk('local')->put($path, "fake png bytes {$n}");
            ChatAttachment::create([
                'chat_session_id'      => $session->id,
                'parent_attachment_id' => $pdf->id,
                'type'                 => 'image',
                'original_name'        => "chart.pdf — page {$n}",
                'stored_path'          => $path,
                'mime_type'            => 'image/png',
                'size'                 => 20,
                'extracted_text'       => '',
                'status'               => 'ready',
            ]);
        }

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'I see two chart pages.']]],
                'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $this->call('POST', '/ai-chat/api/stream', [
            'message'         => 'What do these show?',
            'session_id'      => $session->id,
            'attachment_ids'  => [$pdf->id],
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream'])->assertOk()->streamedContent();

        // Two separate calls happen for a session's first message (auto-title,
        // then the real reply) — only the real reply carries the vision
        // content, on whichever message in its array is the actual user
        // turn (index varies with whether a system message precedes it).
        Http::assertSent(function ($request) {
            foreach ($request->data()['messages'] ?? [] as $msg) {
                if (!is_array($msg['content'] ?? null)) {
                    continue;
                }
                $imageBlocks = array_filter($msg['content'], fn ($b) => ($b['type'] ?? '') === 'image_url');
                if (count($imageBlocks) === 2) {
                    return true;
                }
            }
            return false;
        });
    }
}
