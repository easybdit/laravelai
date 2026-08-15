<?php

namespace EasyAI\LaravelAI\Chat\Controllers;

use EasyAI\LaravelAI\Chat\Models\ChatAttachment;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Chat\Support\PdfPageRenderer;
use EasyAI\LaravelAI\Chat\Support\TextExtractor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-message chat attachments (images for vision, documents for
 * text-extraction) — the Laravel port of the WordPress plugin's v2.7.0
 * upload endpoint. Distinct from ProjectFileController: these are
 * per-message, tied to a ChatSession, and never touch the RAG tables.
 */
class ChatAttachmentController extends Controller
{
    public function store(Request $request)
    {
        if (!config('ai.chat.attachments.enabled', false)) {
            return response()->json(['error' => 'Attachments are not enabled.'], 403);
        }
        if (config('ai.chat.disable_storage', false)) {
            return response()->json(['error' => 'Attachments are unavailable while storage is disabled.'], 403);
        }

        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $guestToken = ChatIdentity::ensureGuestToken($guestToken);

        $images = config('ai.chat.attachments.allowed_images', ['png', 'jpg', 'jpeg', 'webp', 'gif']);
        $docs   = config('ai.chat.attachments.allowed_docs', ['txt', 'md', 'pdf']);

        $request->validate([
            'file'       => 'required|file|max:' . (max(
                config('ai.chat.attachments.max_image_mb', 5),
                config('ai.chat.attachments.max_doc_mb', 10)
            ) * 1024),
            'session_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $type = match (true) {
            in_array($ext, $images, true) => 'image',
            in_array($ext, $docs, true)   => 'document',
            default => null,
        };

        if (!$type) {
            return response()->json(['error' => 'Unsupported file type. Allowed: images (' . implode(', ', $images) . ') and documents (' . implode(', ', $docs) . ').'], 422);
        }

        $maxMb = $type === 'image'
            ? config('ai.chat.attachments.max_image_mb', 5)
            : config('ai.chat.attachments.max_doc_mb', 10);
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return response()->json(['error' => "File too large. Maximum size is {$maxMb} MB."], 422);
        }

        // Resolve or create the session this attachment belongs to.
        $session = $request->filled('session_id') ? ChatSession::find($request->integer('session_id')) : null;
        if (!$session || !$session->isOwnedBy($userId, $guestToken)) {
            $session = ChatSession::create([
                'title'       => 'New Chat',
                'user_id'     => $userId,
                'guest_token' => $userId ? null : $guestToken,
            ]);
        }

        $path = $file->store('chat-attachments/' . $session->id, 'local');

        $extractedText = '';
        if ($type === 'document') {
            try {
                $extractedText = mb_substr(
                    TextExtractor::extract(Storage::disk('local')->path($path), $file->getMimeType()),
                    0,
                    20000
                );
                if (trim($extractedText) === '') {
                    throw new \RuntimeException('File is empty or could not be read.');
                }
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        $attachment = ChatAttachment::create([
            'chat_session_id' => $session->id,
            'type'            => $type,
            'original_name'   => $file->getClientOriginalName(),
            'stored_path'     => $path,
            'mime_type'       => $file->getMimeType(),
            'size'            => $file->getSize(),
            'extracted_text'  => $extractedText,
            'status'          => 'ready',
        ]);

        if ($type === 'document' && str_contains($file->getMimeType(), 'pdf') && config('ai.chat.attachments.pdf_vision_enabled', false)) {
            $this->renderPdfPagesAsAttachments($attachment, Storage::disk('local')->path($path), $file->getClientOriginalName());
        }

        return response()->json([
            'id'          => $attachment->id,
            'type'        => $attachment->type,
            'name'        => $attachment->original_name,
            'size'        => $attachment->size,
            'session_id'  => $session->id,
            'preview_url' => $type === 'image' ? route('ai-chat.attachments.view', $attachment) . '?t=' . Str::random(8) : null,
        ]);
    }

    /**
     * Renders a just-uploaded PDF's pages (PdfPageRenderer,
     * config('ai.chat.attachments.pdf_vision_max_pages')) and stores each as
     * its own ChatAttachment, linked back via parent_attachment_id.
     * resolveAttachments() picks these up automatically the next time
     * $parent is attached to a message — the frontend never needs to know
     * these rows exist. Deliberately non-fatal: a rendering failure (e.g.
     * imagick not actually installed despite the config flag being on)
     * still leaves the upload itself successful — the PDF's plain-text
     * extraction, unaffected by any of this, is still attached and usable.
     */
    private function renderPdfPagesAsAttachments(ChatAttachment $parent, string $pdfPath, string $originalName): void
    {
        try {
            $pages = PdfPageRenderer::render($pdfPath, (int) config('ai.chat.attachments.pdf_vision_max_pages', 5));
        } catch (\Throwable $e) {
            Log::warning("laraveleasyai: PDF page rendering skipped for attachment #{$parent->id}: {$e->getMessage()}");
            return;
        }

        foreach ($pages as $i => $bytes) {
            $pagePath = "chat-attachments/{$parent->chat_session_id}/" . Str::uuid() . '.png';
            Storage::disk('local')->put($pagePath, $bytes);

            ChatAttachment::create([
                'chat_session_id'       => $parent->chat_session_id,
                'parent_attachment_id'  => $parent->id,
                'type'                  => 'image',
                'original_name'         => "{$originalName} — page " . ($i + 1),
                'stored_path'           => $pagePath,
                'mime_type'             => 'image/png',
                'size'                  => strlen($bytes),
                'extracted_text'        => '',
                'status'                => 'ready',
            ]);
        }
    }

    public function show(Request $request, ChatAttachment $attachment)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $session = $attachment->session;

        if (!$session || !$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        $path = Storage::disk('local')->path($attachment->stored_path);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, ['Content-Type' => $attachment->mime_type]);
    }

    public function destroy(Request $request, ChatAttachment $attachment)
    {
        [$userId, $guestToken] = ChatIdentity::resolve($request);
        $session = $attachment->session;

        if (!$session || !$session->isOwnedBy($userId, $guestToken)) {
            abort(403);
        }

        // A PDF's rendered page images (see renderPdfPagesAsAttachments())
        // have no route of their own the frontend ever calls delete on —
        // deleting the parent must take them with it, or they'd orphan.
        foreach ($attachment->pageImages as $page) {
            Storage::disk('local')->delete($page->stored_path);
            $page->delete();
        }

        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }
}
