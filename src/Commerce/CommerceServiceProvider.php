<?php

namespace EasyAI\LaravelAI\Commerce;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the three schema-agnostic commerce assistant endpoints. Binds
 * no default implementations for OrderResolver/ProductResolver/
 * StoreAnalyticsResolver and creates no database tables — every response
 * is empty/501 until the host app binds its own resolver, by design, so
 * installing this package never assumes or conflicts with any particular
 * e-commerce schema.
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/commerce.php');
    }
}
