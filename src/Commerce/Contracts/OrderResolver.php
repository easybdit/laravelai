<?php

namespace EasyAI\LaravelAI\Commerce\Contracts;

/**
 * Bind your own implementation in your app's service provider:
 *
 *   $this->app->bind(OrderResolver::class, \App\Services\MyOrderResolver::class);
 *
 * Both methods are ownership-checking by design — the resolver, not this
 * package, decides whether the email/user genuinely owns the order. Never
 * return an order the caller hasn't proven they own.
 */
interface OrderResolver
{
    /**
     * Guest verification path. Must return null unless $email genuinely
     * matches the order's billing/contact email — never look up an order
     * by number alone.
     *
     * @return OrderArray|null
     */
    public function findByNumberAndEmail(string $orderNumber, string $email): ?array;

    /**
     * Authenticated path — only ever return orders belonging to $userId.
     * $orderNumber narrows to one order when given; omit it to return the
     * user's recent orders.
     *
     * @return array<int, OrderArray>
     */
    public function findForUser(int|string $userId, ?string $orderNumber = null): array;

    /**
     * Order shape (both methods), documented here since PHP has no proper
     * shape/type alias syntax:
     *
     * array{
     *     id: int|string, number: string, status: string, total: float,
     *     currency?: string, placed_at?: string,
     *     items: array<int, array{name: string, qty: int, price: float}>
     * }
     */
}
