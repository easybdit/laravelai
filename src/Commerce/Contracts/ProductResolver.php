<?php

namespace EasyAI\LaravelAI\Commerce\Contracts;

/**
 * Bind your own implementation of this in your app's service provider:
 *
 *   $this->app->bind(ProductResolver::class, \App\Services\MyProductResolver::class);
 *
 * This package never queries a products table directly — it only knows
 * this contract — so it works against WooCommerce, a custom Eloquent
 * catalog, a remote API, anything, and never creates a table that could
 * collide with your own.
 */
interface ProductResolver
{
    /**
     * Natural-language-derived search criteria (all optional except that at
     * least one is usually present):
     *
     *   ['keyword' => 'red dress', 'category' => 'dresses', 'min_price' => 20,
     *    'max_price' => 50, 'limit' => 4]
     *
     * @return array<int, array{
     *     id: int|string, name: string, price: float, currency?: string,
     *     stock?: int|string, url?: string, image?: string, description?: string
     * }>
     */
    public function search(array $criteria): array;

    /**
     * @return array{
     *     id: int|string, name: string, price: float, currency?: string,
     *     stock?: int|string, description?: string, variations?: array
     * }|null
     */
    public function find(int|string $productId): ?array;
}
