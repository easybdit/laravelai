<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConversationExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): ChatSession
    {
        $session = ChatSession::create(['title' => 'Export Test Chat']);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => 'What is **Laravel**?']);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => "Laravel is a *PHP framework*.\n\n- Elegant syntax\n- Great docs\n\n```php\necho 'hi';\n```"]);

        return $session;
    }

    public function test_pdf_export_downloads_a_real_pdf(): void
    {
        $session = $this->makeSession();

        $response = $this->get("/ai-chat/api/sessions/{$session->id}/export/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_docx_export_downloads_a_real_word_document(): void
    {
        $session = $this->makeSession();

        $response = $this->get("/ai-chat/api/sessions/{$session->id}/export/docx");

        $response->assertOk();
        $response->assertHeader('Content-Type', \EasyAI\LaravelAI\Chat\Support\Export\DocxExporter::MIME);
        // .docx is a zip container — PK\x03\x04 is the zip local-file-header magic bytes.
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_xlsx_export_downloads_a_real_spreadsheet(): void
    {
        $session = $this->makeSession();

        $response = $this->get("/ai-chat/api/sessions/{$session->id}/export/xlsx");

        $response->assertOk();
        $response->assertHeader('Content-Type', \EasyAI\LaravelAI\Chat\Support\Export\XlsxExporter::MIME);
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_pptx_export_downloads_a_real_presentation(): void
    {
        $session = $this->makeSession();

        $response = $this->get("/ai-chat/api/sessions/{$session->id}/export/pptx");

        $response->assertOk();
        $response->assertHeader('Content-Type', \EasyAI\LaravelAI\Chat\Support\Export\PptxExporter::MIME);
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_export_refuses_a_session_you_do_not_own(): void
    {
        $mine = $this->makeSession();
        $mine->update(['guest_token' => str_repeat('a', 40)]); // owned by a specific guest, not "anyone"

        $this->get("/ai-chat/api/sessions/{$mine->id}/export/pdf")->assertStatus(403);
    }

    public function test_unknown_format_is_a_404(): void
    {
        $session = $this->makeSession();

        $this->get("/ai-chat/api/sessions/{$session->id}/export/rtf")->assertStatus(404);
    }
}
