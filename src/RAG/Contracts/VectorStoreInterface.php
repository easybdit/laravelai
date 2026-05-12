<?php
namespace EasyAI\LaravelAI\RAG\Contracts;

interface VectorStoreInterface
{
    public function store(string $content, array $vector, string $source): void;
    public function all(): \Illuminate\Support\Collection;
    public function truncate(): void;
}