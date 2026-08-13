<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Commerce\Contracts\OrderResolver;
use EasyAI\LaravelAI\Commerce\Contracts\ProductResolver;
use EasyAI\LaravelAI\Commerce\Contracts\StoreAnalyticsResolver;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/**
 * Exercises the commerce endpoints purely against fake in-memory resolvers
 * — proving the contracts are sufficient to drive all three assistants
 * without this package ever touching a real e-commerce schema.
 */
class CommerceTest extends TestCase
{
    private function fakeUser(): Authenticatable
    {
        return new class implements Authenticatable {
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier() { return 42; }
            public function getAuthPasswordName() { return 'password'; }
            public function getAuthPassword() { return 'x'; }
            public function getRememberToken() { return null; }
            public function setRememberToken($value) {}
            public function getRememberTokenName() { return 'remember_token'; }
        };
    }

    public function test_store_assistant_refuses_without_a_defined_gate(): void
    {
        $this->actingAs($this->fakeUser());

        $response = $this->postJson('/ai-chat/api/commerce/store-assistant', ['question' => 'How much revenue this month?']);

        $response->assertStatus(403);
    }

    public function test_store_assistant_refuses_without_a_bound_resolver_even_with_gate_allowed(): void
    {
        Gate::define('view-store-assistant', fn () => true);
        $this->actingAs($this->fakeUser());

        $response = $this->postJson('/ai-chat/api/commerce/store-assistant', ['question' => 'How much revenue this month?']);

        $response->assertStatus(501);
    }

    public function test_store_assistant_answers_using_bound_resolver_data(): void
    {
        Gate::define('view-store-assistant', fn () => true);
        $this->actingAs($this->fakeUser());

        $this->app->bind(StoreAnalyticsResolver::class, fn () => new class implements StoreAnalyticsResolver {
            public function revenue(string $from, string $to): array
            {
                return ['total' => 1234.56, 'currency' => 'USD', 'order_count' => 18];
            }
            public function orders(string $from, string $to): array { return ['total' => 0, 'by_status' => []]; }
            public function topProducts(string $from, string $to, int $limit): array { return []; }
            public function lowStock(int $threshold, int $limit): array { return []; }
            public function newCustomers(string $from, string $to, int $limit): array { return ['count' => 0, 'sample' => []]; }
            public function summary(): array { return []; }
        });

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['message' => ['content' => '{"query":{"type":"revenue"}}'], 'done' => true])
                ->push(['message' => ['content' => 'Revenue this period was $1,234.56 across 18 orders.'], 'done' => true]),
        ]);

        $response = $this->postJson('/ai-chat/api/commerce/store-assistant', ['question' => 'How much revenue this month?']);

        $response->assertOk()->assertJson(['answer' => 'Revenue this period was $1,234.56 across 18 orders.']);
    }

    public function test_product_assistant_refuses_without_a_bound_resolver(): void
    {
        $response = $this->postJson('/ai-chat/api/commerce/products/ask', ['question' => 'red dress under $50']);

        $response->assertStatus(501);
    }

    public function test_product_assistant_returns_reply_and_matched_products(): void
    {
        $this->app->bind(ProductResolver::class, fn () => new class implements ProductResolver {
            public function search(array $criteria): array
            {
                return [['id' => 1, 'name' => 'Red Summer Dress', 'price' => 42.0, 'currency' => 'USD']];
            }
            public function find(int|string $productId): ?array { return null; }
        });

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['content' => 'Here are a few options! {"search":{"keyword":"red dress","min_price":0,"max_price":50}}'],
                'done'    => true,
            ]),
        ]);

        $response = $this->postJson('/ai-chat/api/commerce/products/ask', ['question' => 'red dress under $50']);

        $response->assertOk()
            ->assertJsonPath('reply', 'Here are a few options!')
            ->assertJsonPath('products.0.name', 'Red Summer Dress');
    }

    public function test_order_status_refuses_without_a_bound_resolver(): void
    {
        $response = $this->postJson('/ai-chat/api/commerce/orders/ask', [
            'question' => 'Where is my order?', 'order_number' => '1001', 'email' => 'a@example.com',
        ]);

        $response->assertStatus(501);
    }

    public function test_order_status_rejects_unverified_guest(): void
    {
        $this->app->bind(OrderResolver::class, fn () => new class implements OrderResolver {
            public function findByNumberAndEmail(string $orderNumber, string $email): ?array
            {
                return $email === 'real-owner@example.com' ? ['id' => 1, 'number' => '1001', 'status' => 'shipped', 'total' => 42.0, 'items' => []] : null;
            }
            public function findForUser(int|string $userId, ?string $orderNumber = null): array { return []; }
        });

        $response = $this->postJson('/ai-chat/api/commerce/orders/ask', [
            'question' => 'Where is my order?', 'order_number' => '1001', 'email' => 'wrong@example.com',
        ]);

        $response->assertOk()->assertJsonPath('reply', fn ($reply) => str_contains($reply, "couldn't find"));
    }

    public function test_order_status_answers_for_verified_guest(): void
    {
        $this->app->bind(OrderResolver::class, fn () => new class implements OrderResolver {
            public function findByNumberAndEmail(string $orderNumber, string $email): ?array
            {
                return ['id' => 1, 'number' => '1001', 'status' => 'shipped', 'total' => 42.0, 'items' => [['name' => 'Red Dress', 'qty' => 1, 'price' => 42.0]]];
            }
            public function findForUser(int|string $userId, ?string $orderNumber = null): array { return []; }
        });

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['content' => 'Order #1001 has shipped! It contains 1x Red Dress.'],
                'done'    => true,
            ]),
        ]);

        $response = $this->postJson('/ai-chat/api/commerce/orders/ask', [
            'question' => 'Where is my order?', 'order_number' => '1001', 'email' => 'real-owner@example.com',
        ]);

        $response->assertOk()->assertJsonPath('reply', 'Order #1001 has shipped! It contains 1x Red Dress.');
    }
}
