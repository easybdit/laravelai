<?php

use Illuminate\Support\Facades\Route;
use EasyAI\LaravelAI\Chat\Controllers\AIChatController;
use EasyAI\LaravelAI\Chat\Controllers\ProjectController;
use EasyAI\LaravelAI\Chat\Controllers\ProjectFileController;

Route::prefix('ai-chat')->name('ai-chat.')->group(function () {

    Route::get('/', [AIChatController::class, 'index'])->name('index');

    Route::prefix('api')->group(function () {

        // ── Chat sessions ────────────────────────────────────────────────
        Route::post('sessions',             [AIChatController::class, 'newSession'])->name('sessions.new');
        Route::delete('sessions/{session}', [AIChatController::class, 'deleteSession'])->name('sessions.delete');
        Route::get('stream',                [AIChatController::class, 'stream'])->name('stream');
        Route::post('provider',             [AIChatController::class, 'switchProvider'])->name('provider');

        // ── Projects ─────────────────────────────────────────────────────
        Route::get('projects',             [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects',            [ProjectController::class, 'store'])->name('projects.store');
        Route::delete('projects/{project}',[ProjectController::class, 'destroy'])->name('projects.destroy');

        // ── Project files ─────────────────────────────────────────────────
        Route::post('projects/{project}/files',              [ProjectFileController::class, 'store'])->name('projects.files.store');
        Route::delete('projects/{project}/files/{file}',     [ProjectFileController::class, 'destroy'])->name('projects.files.destroy');
        Route::get('projects/{project}/files',               [ProjectFileController::class, 'index'])->name('projects.files.index');
    });
});
