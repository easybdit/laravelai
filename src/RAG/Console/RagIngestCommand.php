<?php
namespace EasyAI\LaravelAI\Console;

use EasyAI\LaravelAI\RAG\RAGManager;
use Illuminate\Console\Command;

class RagIngestCommand extends Command
{
    protected $signature   = 'ai:rag:ingest {path : File or directory} {--source= : Label} {--flush : Clear first}';
    protected $description = 'Ingest documents into RAG vector store';

    public function handle(RAGManager $rag): void
    {
        if ($this->option('flush')) {
            $rag->flush();
            $this->info('Vector store cleared.');
        }

        $path  = $this->argument('path');
        $files = is_dir($path)
            ? collect(glob($path . '/*.{txt,md}', GLOB_BRACE))
            : collect([$path]);

        foreach ($files as $file) {
            if (!file_exists($file)) { $this->warn("Not found: {$file}"); continue; }
            $count = $rag->ingest(file_get_contents($file), $this->option('source') ?: basename($file));
            $this->info("✓ {$file} → {$count} chunks");
        }

        $this->info('Done!');
    }
}