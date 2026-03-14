<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Verse;
use App\Services\OllamaService;
use App\Services\VectorStoreService;

class VectorizeVerses extends Command
{
    protected $signature = 'bible:vectorize {--version=BSB : The version to vectorize} {--chunk=50 : Batch size for embeddings}';
    protected $description = 'Vectorize existing Bible verses from MongoDB into ChromaDB';

    public function handle()
    {
        $ollama = app(OllamaService::class);
        $vectorStore = app(VectorStoreService::class);

        $version = $this->option('version');
        $chunkSize = (int)$this->option('chunk');

        $this->info("Starting vectorization for {$version}...");

        $total = Verse::where('version', $version)->count();
        $this->info("Total verses to process: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Verse::where('version', $version)->chunk($chunkSize, function ($verses) use ($ollama, $vectorStore, $bar, $version) {
            $batchVerses = [];
            $batchEmbeddings = [];
            $batchMetadatas = [];
            $batchIds = [];

            foreach ($verses as $verse) {
                $fullReference = $verse->full_reference;
                $text = $verse->text;
                $content = "{$fullReference} ({$version}): {$text}";

                try {
                    $embedding = $ollama->embed($content);
                    
                    if (!empty($embedding)) {
                        $batchVerses[] = $content;
                        $batchEmbeddings[] = $embedding;
                        $batchMetadatas[] = [
                            'version' => $version,
                            'book' => $verse->book,
                            'chapter' => $verse->chapter,
                            'verse' => $verse->verse,
                            'reference' => $fullReference,
                        ];
                        $batchIds[] = "{$version}_{$verse->book}_{$verse->chapter}_{$verse->verse}";
                    }
                } catch (\Exception $e) {
                    $this->error("\nError embedding {$fullReference}: " . $e->getMessage());
                    // Circuit breaker might trigger here, we should probably wait or exit
                    if (str_contains($e->getMessage(), 'Circuit Breaker')) {
                        $this->warn("Circuit breaker active. Sleeping for 1 minute...");
                        sleep(60);
                    }
                }
            }

            if (!empty($batchVerses)) {
                try {
                    $vectorStore->addDocuments('bible_verses', $batchVerses, $batchMetadatas, $batchIds, $batchEmbeddings);
                } catch (\Exception $e) {
                    $this->error("\nError adding to ChromaDB: " . $e->getMessage());
                }
            }

            $bar->advance(count($verses));
            
            // Subtle sleep to prevent CPU/GPU hammering as requested
            usleep(200000); // 200ms
        });

        $bar->finish();
        $this->info("\nVectorization completed!");
    }
}
