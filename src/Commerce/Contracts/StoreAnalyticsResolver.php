<?php

namespace EasyAI\LaravelAI\Commerce\Contracts;

/**
 * Powers the admin-only "Ask Your Store" assistant. Bind your own
 * implementation:
 *
 *   $this->app->bind(StoreAnalyticsResolver::class, \App\Services\MyStoreAnalytics::class);
 *
 * Every method is read-only aggregate data — never row-level customer PII
 * beyond what a store owner would already see in their own admin panel.
 * $from / $to are 'Y-m-d' date strings.
 */
interface StoreAnalyticsResolver
{
    /** @return array{total: float, currency?: string, order_count: int} */
    public function revenue(string $from, string $to): array;

    /** @return array{total: int, by_status: array<string, int>} */
    public function orders(string $from, string $to): array;

    /** @return array<int, array{name: string, qty_sold: int, revenue: float}> */
    public function topProducts(string $from, string $to, int $limit): array;

    /** @return array<int, array{name: string, stock: int}> */
    public function lowStock(int $threshold, int $limit): array;

    /** @return array{count: int, sample: array<int, string>} */
    public function newCustomers(string $from, string $to, int $limit): array;

    /** Freeform store-health snapshot — whatever your store considers "the basics." */
    public function summary(): array;
}
