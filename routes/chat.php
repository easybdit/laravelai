<?php

use Illuminate\Support\Facades\Route;
use EasyAI\LaravelAI\Chat\Controllers\AIChatController;
use EasyAI\LaravelAI\Chat\Controllers\AnalyticsController;
use EasyAI\LaravelAI\Chat\Controllers\ChatAttachmentController;
use EasyAI\LaravelAI\Chat\Controllers\ProjectController;
use EasyAI\LaravelAI\Chat\Controllers\ProjectFileController;
use EasyAI\LaravelAI\Chat\Controllers\SettingsController;

Route::prefix('ai-chat')->name('ai-chat.')->group(function () {

    Route::get('/', [AIChatController::class, 'index'])->name('index');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // Auth + Gate-checked inside the controller (fail-closed) — kept out of
    // the api/ group since these are pages, not JSON endpoints.
    Route::get('/settings',  [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::prefix('api')->group(function () {

        // ── Chat sessions ────────────────────────────────────────────────
        Route::post('sessions',             [AIChatController::class, 'newSession'])->name('sessions.new');
        Route::delete('sessions/{session}', [AIChatController::class, 'deleteSession'])->name('sessions.delete');
        Route::post('stream',                [AIChatController::class, 'stream'])->name('stream');
        Route::post('provider',             [AIChatController::class, 'switchProvider'])->name('provider');
        Route::get('captcha',               [AIChatController::class, 'captcha'])->name('captcha');
        Route::post('messages/{message}/feedback', [AIChatController::class, 'feedback'])->name('messages.feedback');

        // ── Attachments ──────────────────────────────────────────────────
        Route::post('attachments',                [ChatAttachmentController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{attachment}',     [ChatAttachmentController::class, 'show'])->name('attachments.view');
        Route::delete('attachments/{attachment}',  [ChatAttachmentController::class, 'destroy'])->name('attachments.destroy');

        // ── Projects ─────────────────────────────────────────────────────
        Route::get('projects',             [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects',            [ProjectController::class, 'store'])->name('projects.store');
        Route::delete('projects/{project}',[ProjectController::class, 'destroy'])->name('projects.destroy');

        // ── Project files ─────────────────────────────────────────────────
        Route::get('projects/{project}/files',                    [ProjectFileController::class, 'index'])->name('projects.files.index');
        Route::post('projects/{project}/files',                   [ProjectFileController::class, 'store'])->name('projects.files.store');
        Route::post('projects/{project}/files/{file}/reprocess',  [ProjectFileController::class, 'reprocess'])->name('projects.files.reprocess');
        Route::delete('projects/{project}/files/{file}',          [ProjectFileController::class, 'destroy'])->name('projects.files.destroy');

        // ── RAG test-query tool ─────────────────────────────────────────────
        Route::post('rag/test', [ProjectController::class, 'testQuery'])->name('rag.test');
    });
});
