<?php
namespace EasyAI\LaravelAI\Console;

use EasyAI\LaravelAI\RAG\RAGManager;
use Illuminate\Console\Command;

class RagIngestCommand extends Command
{
    protected $signature   = 'ai:rag:ingest {path : File or directory path} {--source= : Label for this content} {--flush : Clear existing documents first}';
    protected $description = 'Ingest documents into RAG vector store';

    public function handle(RAGManager $rag): void
    {
        if ($this->option('flush')) {
            $rag->flush();
            $this->info('Vector store cleared.');
        }

        $path = $this->argument('path');
        $files = is_dir($path)
            ? collect(glob($path . '/*.{txt,md}', GLOB_BRACE))
            : collect([$path]);

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("Not found: {$file}");
                continue;
            }

            $source = $this->option('source') ?: basename($file);
            $count  = $rag->ingest(file_get_contents($file), $source);
            $this->info("✓ {$file} → {$count} chunks");
        }

        $this->info('Done!');
    }
}