<?php

use Illuminate\Support\Facades\Route;
use EasyAI\LaravelAI\Commerce\Controllers\OrderStatusController;
use EasyAI\LaravelAI\Commerce\Controllers\ProductAssistantController;
use EasyAI\LaravelAI\Commerce\Controllers\StoreAssistantController;

// Always registered — each controller checks app()->bound(...Resolver::class)
// at request time and returns a clear 501 if the host app hasn't bound an
// implementation yet, rather than conditionally registering routes based on
// boot-time container state (which route caching can make unreliable).
Route::prefix('ai-chat/api/commerce')->name('ai-chat.commerce.')->group(function () {
    Route::post('store-assistant', [StoreAssistantController::class, 'ask'])->name('store');
    Route::post('products/ask',    [ProductAssistantController::class, 'ask'])->name('products');
    Route::post('orders/ask',      [OrderStatusController::class, 'ask'])->name('orders');
});
