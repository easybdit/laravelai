<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\RAG\VectorStores\PgVectorStore;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Verifies the exact SQL shape PgVectorStore constructs against the DB
 * facade — no live Postgres/pgvector connection needed or assumed, same
 * spirit as TavilySearchProviderTest asserting the HTTP call shape via
 * Http::fake() without needing a live Tavily account.
 */
class PgVectorStoreTest extends TestCase
{
    public function test_upsert_inserts_with_vector_cast(): void
    {
        DB::shouldReceive('insert')
            ->once()
            ->withArgs(function (string $sql, array $bindings) {
                return str_contains($sql, 'insert into ai_document_vectors')
                    && str_contains($sql, '?::vector')
                    && $bindings[0] === 'hello world'
                    && $bindings[1] === 'docs'
                    && $bindings[2] === '[0.1,0.2,0.3]';
            });

        (new PgVectorStore())->upsert('hello world', 'docs', [0.1, 0.2, 0.3]);
    }

    public function test_search_builds_cosine_distance_query_scoped_to_source(): void
    {
        $row = (object) ['content' => 'match', 'source' => 'docs', 'score' => 0.9];

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings) {
                return str_contains($sql, '1 - (embedding <=> ?::vector) as score')
                    && str_contains($sql, 'where source = ?')
                    && str_contains($sql, 'order by embedding <=> ?::vector limit ?')
                    && $bindings === ['[1,0,0]', 'docs', '[1,0,0]', 5];
            })
            ->andReturn([$row]);

        $results = (new PgVectorStore())->search([1, 0, 0], 'docs', 5);

        $this->assertSame([
            ['content' => 'match', 'source' => 'docs', 'score' => 0.9],
        ], $results);
    }

    public function test_search_without_source_omits_the_where_clause(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings) {
                return !str_contains($sql, 'where source')
                    && $bindings === ['[1,0,0]', '[1,0,0]', 3];
            })
            ->andReturn([]);

        $results = (new PgVectorStore())->search([1, 0, 0], null, 3);

        $this->assertSame([], $results);
    }

    public function test_search_returns_empty_array_instead_of_throwing_on_sql_failure(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('extension "vector" is not available'));

        $results = (new PgVectorStore())->search([1, 0, 0], null, 3);

        $this->assertSame([], $results);
    }

    public function test_delete_with_source_scopes_to_that_source(): void
    {
        DB::shouldReceive('delete')
            ->once()
            ->with('delete from ai_document_vectors where source = ?', ['docs']);

        (new PgVectorStore())->delete('docs');
    }

    public function test_delete_without_source_truncates(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with('truncate table ai_document_vectors');

        (new PgVectorStore())->delete();
    }

    public function test_count_with_source_scopes_to_that_source(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->with('select count(*) as c from ai_document_vectors where source = ?', ['docs'])
            ->andReturn((object) ['c' => 7]);

        $this->assertSame(7, (new PgVectorStore())->count('docs'));
    }

    public function test_count_without_source_counts_everything(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->with('select count(*) as c from ai_document_vectors')
            ->andReturn((object) ['c' => 42]);

        $this->assertSame(42, (new PgVectorStore())->count());
    }

    public function test_custom_table_and_dimensions_are_respected_in_sql(): void
    {
        DB::shouldReceive('insert')
            ->once()
            ->withArgs(fn (string $sql) => str_contains($sql, 'insert into my_vectors'));

        (new PgVectorStore(table: 'my_vectors', dimensions: 768))->upsert('x', 'y', [1, 2]);
    }
}
